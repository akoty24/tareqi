<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\Trip;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Real row-lock behaviour. Needs MySQL/MariaDB (SQLite has no SELECT ... FOR UPDATE):
 *   DB_CONNECTION=mysql DB_DATABASE=mishwar_testing php artisan test --filter=BookingConcurrencyTest
 *
 * Uses DatabaseMigrations (not RefreshDatabase) because a second connection
 * must see committed rows.
 */
class BookingConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Row-level locking test requires MySQL/MariaDB.');
        }
    }

    public function test_second_booking_waits_for_the_lock_and_then_sees_updated_seats(): void
    {
        $trip = $this->publishedTrip(['total_seats' => 3, 'available_seats' => 3, 'auto_confirm_bookings' => true]);
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Second, independent connection = "request B".
        $config = config('database.connections.'.config('database.default'));
        config(['database.connections.request_b' => $config]);
        $requestB = DB::connection('request_b');

        // Request A opens a transaction and locks the trip row (what BookingService does).
        DB::beginTransaction();
        Trip::query()->lockForUpdate()->find($trip->id);

        // Request B cannot acquire the same row lock while A holds it.
        $requestB->statement('SET SESSION innodb_lock_wait_timeout = 1');
        $blocked = false;
        try {
            $requestB->transaction(fn ($db) => $db->table('trips')->where('id', $trip->id)->lockForUpdate()->first());
        } catch (QueryException $e) {
            $blocked = str_contains($e->getMessage(), 'Lock wait timeout');
        }
        $this->assertTrue($blocked, 'Request B should have been blocked by the row lock.');

        DB::rollBack();

        // Now run both bookings for real: A takes 2 seats, B asks for 2 and must fail.
        $service = app(BookingService::class);
        $service->create($trip, $userA, 2);

        $this->expectException(BusinessRuleException::class);
        try {
            $service->create($trip, $userB, 2);
        } finally {
            $this->assertSame(1, $trip->fresh()->available_seats);
            $this->assertSame(1, $trip->bookings()->count());
        }
    }
}
