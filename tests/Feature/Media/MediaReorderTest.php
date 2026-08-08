<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use Modules\Media\app\Models\Media;

class MediaReorderTest extends MediaTestCase
{
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        for ($i = 1; $i <= 3; $i++) {
            $this->ids[$i] = $this->upload($this->ownerUser)->assertStatus(201)->json('data.id');
        }
    }

    public function test_owner_can_reorder_post_media(): void
    {
        $this->patchJson($this->postReorderUri($this->communityA, $this->post), [
            'items' => [
                ['id' => $this->ids[1], 'position' => 3],
                ['id' => $this->ids[2], 'position' => 1],
                ['id' => $this->ids[3], 'position' => 2],
            ],
        ], $this->token($this->ownerUser))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Media reordered successfully.');

        $ordered = Media::where('mediable_type', 'post')->orderBy('position')->pluck('position')->all();

        $this->assertSame([1, 2, 3], $ordered);
        $this->assertSame(1, (int) Media::find($this->ids[2])->position);
        $this->assertSame(3, (int) Media::find($this->ids[1])->position);
    }

    public function test_duplicate_positions_are_422(): void
    {
        $this->patchJson($this->postReorderUri($this->communityA, $this->post), [
            'items' => [
                ['id' => $this->ids[1], 'position' => 1],
                ['id' => $this->ids[2], 'position' => 1],
                ['id' => $this->ids[3], 'position' => 2],
            ],
        ], $this->token($this->ownerUser))
            ->assertStatus(422);
    }

    public function test_duplicate_ids_are_422(): void
    {
        $this->patchJson($this->postReorderUri($this->communityA, $this->post), [
            'items' => [
                ['id' => $this->ids[1], 'position' => 1],
                ['id' => $this->ids[1], 'position' => 2],
                ['id' => $this->ids[3], 'position' => 3],
            ],
        ], $this->token($this->ownerUser))
            ->assertStatus(422);
    }

    public function test_position_outside_range_is_422(): void
    {
        $this->patchJson($this->postReorderUri($this->communityA, $this->post), [
            'items' => [
                ['id' => $this->ids[1], 'position' => 1],
                ['id' => $this->ids[2], 'position' => 6],
                ['id' => $this->ids[3], 'position' => 3],
            ],
        ], $this->token($this->ownerUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.1.position']);
    }

    public function test_foreign_parent_media_rejected(): void
    {
        // An id that does not belong to this parent must be rejected.
        $this->patchJson($this->postReorderUri($this->communityA, $this->post), [
            'items' => [
                ['id' => $this->ids[1], 'position' => 1],
                ['id' => 999999, 'position' => 2],
                ['id' => $this->ids[3], 'position' => 3],
            ],
        ], $this->token($this->ownerUser))
            ->assertStatus(422);
    }

    public function test_cross_community_parent_is_404(): void
    {
        $this->patchJson($this->postReorderUri($this->communityB, $this->post), [
            'items' => [
                ['id' => $this->ids[1], 'position' => 1],
                ['id' => $this->ids[2], 'position' => 2],
                ['id' => $this->ids[3], 'position' => 3],
            ],
        ], $this->token($this->ownerUser))
            ->assertStatus(404);
    }

    public function test_non_owner_cannot_reorder(): void
    {
        $this->patchJson($this->postReorderUri($this->communityA, $this->post), [
            'items' => [
                ['id' => $this->ids[1], 'position' => 1],
                ['id' => $this->ids[2], 'position' => 2],
                ['id' => $this->ids[3], 'position' => 3],
            ],
        ], $this->token($this->secondUser))
            ->assertStatus(403);
    }

    public function test_reorder_is_atomic_rollback_on_failure(): void
    {
        $original = Media::where('mediable_type', 'post')->orderBy('id')->pluck('position')->all();

        // One invalid position makes the whole request fail; no partial change.
        $this->patchJson($this->postReorderUri($this->communityA, $this->post), [
            'items' => [
                ['id' => $this->ids[1], 'position' => 3],
                ['id' => $this->ids[2], 'position' => 1],
                ['id' => $this->ids[3], 'position' => 3],
            ],
        ], $this->token($this->ownerUser))
            ->assertStatus(422);

        $after = Media::where('mediable_type', 'post')->orderBy('id')->pluck('position')->all();

        $this->assertSame($original, $after, 'positions must be unchanged after a failed reorder');
    }

    public function test_anonymous_reorder_is_401(): void
    {
        $this->patchJson($this->postReorderUri($this->communityA, $this->post), [
            'items' => [],
        ])->assertStatus(401);
    }

    protected function upload($user, array $extra = [])
    {
        return $this->postJson($this->postMediaUri($this->communityA, $this->post), array_merge(['image' => $this->validImage()], $extra), $this->token($user));
    }
}
