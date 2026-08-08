<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Modules\Media\app\Models\Media;
use Modules\Media\app\Services\MediaService;
use Modules\Post\app\Models\Post;
use Tests\Support\DatabaseSafetyGuard;
use Tests\TestCase;

/**
 * Proves the five-image ceiling holds under genuine concurrent uploads.
 *
 * This test deliberately does NOT use RefreshDatabase: concurrency requires
 * each forked child to hold its own DB connection observing committed state,
 * which a wrapping transaction would prevent. Setup is committed and cleaned
 * up in tearDown so the shared test database stays pristine for other suites.
 *
 * @group concurrency
 */
class MediaConcurrencyTest extends TestCase
{
    private ?int $communityId = null;
    private ?int $postId = null;
    private ?int $userId = null;

    protected function setUp(): void
    {
        parent::setUp();
        DatabaseSafetyGuard::assertBootedApplicationSafe($this->app);
        Storage::fake('public');

        $community = Community::create([
            'name' => 'Concurrency', 'city' => 'C', 'address' => 'A',
            'total_units' => 1, 'is_active' => true,
        ]);
        $unit = Unit::create([
            'community_id' => $community->id, 'unit_number' => 'U', 'building_name' => 'B',
            'rooms' => 1, 'unit_type' => 'apartment', 'is_active' => true,
        ]);
        $user = User::factory()->resident()->create(['is_active' => true]);
        $resident = Resident::create([
            'user_id' => $user->id, 'unit_id' => $unit->id, 'community_id' => $community->id,
            'residence_type' => 'owner', 'status' => 'active', 'current_marker' => true,
        ]);
        $post = Post::create([
            'community_id' => $community->id, 'resident_id' => $resident->id,
            'category' => 'general', 'content' => 'race',
        ]);

        $this->communityId = (int) $community->id;
        $this->postId = (int) $post->id;
        $this->userId = (int) $user->id;
    }

    protected function tearDown(): void
    {
        if ($this->communityId !== null) {
            DB::table('media')->where('mediable_type', 'post')->delete();
            DB::table('posts')->where('id', $this->postId)->delete();
            DB::table('residents')->where('community_id', $this->communityId)->delete();
            DB::table('community_mangers')->where('community_id', $this->communityId)->delete();
            DB::table('units')->where('community_id', $this->communityId)->delete();
            DB::table('communities')->where('id', $this->communityId)->delete();
            DB::table('personal_access_tokens')->where('tokenable_id', $this->userId)->delete();
            DB::table('users')->where('id', $this->userId)->delete();
        }

        parent::tearDown();
    }

    public function test_concurrent_uploads_cannot_exceed_five(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required for the concurrency race test.');
        }

        $children = 8;
        $results = array_fill(0, $children, null);
        $dir = sys_get_temp_dir().'/media_race_'.uniqid('', true);
        @mkdir($dir, 0777, true);

        for ($i = 0; $i < $children; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->markTestSkipped('pcntl_fork failed.');
            }

            if ($pid === 0) {
                // Child: its own DB connection so it observes committed state.
                DB::disconnect('mysql');

                $post = Post::find($this->postId);
                $user = User::find($this->userId);

                $outcome = 'ERR';
                try {
                    app(MediaService::class)->attach(
                        $post,
                        'post',
                        $user,
                        \Illuminate\Http\UploadedFile::fake()->image("c{$i}.jpg", 10, 10),
                        null,
                        fn () => true,
                    );
                    $outcome = 'OK';
                } catch (\Throwable) {
                    // Limit / position conflict is the expected losing outcome.
                }

                file_put_contents("{$dir}/child_{$i}.txt", $outcome);
                posix_kill(getmypid(), SIGKILL);
            }

            $results[$i] = $pid;
        }

        // Parent: reap all children.
        foreach ($results as $pid) {
            if ($pid !== null) {
                pcntl_waitpid($pid, $status);
            }
        }

        $wins = 0;
        for ($i = 0; $i < $children; $i++) {
            if (is_file("{$dir}/child_{$i}.txt") && file_get_contents("{$dir}/child_{$i}.txt") === 'OK') {
                $wins++;
            }
        }

        $mediaCount = Media::where('mediable_type', 'post')->where('mediable_id', $this->postId)->count();
        $fileCount = count(Storage::disk('public')->files('media'));

        array_map('unlink', glob("{$dir}/*") ?: []);
        @rmdir($dir);

        $this->assertSame(5, $mediaCount, 'at most five media rows may survive a concurrent race');
        $this->assertSame(5, $fileCount, 'no orphan files beyond the five-image ceiling');
        $this->assertSame(5, $wins, 'exactly five uploads win the race');
    }

    public function test_unique_position_constraint_enforced_at_db_level(): void
    {
        // The hard backstop: no two rows for the same parent may share a
        // position, which structurally caps media at five (positions 1..5).
        $this->expectException(\Illuminate\Database\QueryException::class);

        Media::create([
            'mediable_type' => 'post',
            'mediable_id' => $this->postId,
            'uploaded_by' => $this->userId,
            'file_path' => 'media/a.jpg',
            'file_name' => 'a.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 100,
            'disk' => 'public',
            'position' => 1,
        ]);
        Media::create([
            'mediable_type' => 'post',
            'mediable_id' => $this->postId,
            'uploaded_by' => $this->userId,
            'file_path' => 'media/b.jpg',
            'file_name' => 'b.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 100,
            'disk' => 'public',
            'position' => 1,
        ]);
    }
}
