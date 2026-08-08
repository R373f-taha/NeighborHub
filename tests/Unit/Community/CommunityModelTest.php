<?php

declare(strict_types=1);

namespace Tests\Unit\Community;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Unit tests for the community model behavior and attribute configuration.
 *
 * These assertions remain focused on the model layer and do not require
 * HTTP or API interactions.
 */
#[CoversClass(Community::class)]
class CommunityModelTest extends TestCase
{
    public function test_fillable_attributes_allow_expected_fields_only(): void
    {
        $community = new Community();

        $community->fill([
            'name' => 'NeighborHub Community',
            'city' => 'Amman',
            'address' => '123 NeighborHub Ave',
            'total_units' => 45,
            'is_active' => true,
            'unexpected_field' => 'should_not_be_set',
        ]);

        $this->assertSame('NeighborHub Community', $community->name);
        $this->assertSame('Amman', $community->city);
        $this->assertSame('123 NeighborHub Ave', $community->address);
        $this->assertSame(45, $community->total_units);
        $this->assertTrue($community->is_active);
        $this->assertFalse(property_exists($community, 'unexpected_field'));
        $this->assertNull($community->getAttribute('unexpected_field'));
    }

    public function test_active_cast_is_boolean(): void
    {
        $community = new Community([
            'name' => 'NeighborHub Community',
            'city' => 'Amman',
            'address' => '123 NeighborHub Ave',
            'total_units' => 45,
            'is_active' => 1,
        ]);

        $this->assertTrue($community->is_active);
        $this->assertIsBool($community->is_active);
    }

    public function test_relationships_return_expected_related_models(): void
    {
        $community = new Community();

        $this->assertInstanceOf(BelongsToMany::class, $community->managers());
        $this->assertInstanceOf(HasMany::class, $community->units());
        $this->assertInstanceOf(HasManyThrough::class, $community->residents());
        $this->assertInstanceOf(HasMany::class, $community->announcements());
    }
}
