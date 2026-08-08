# Test Patterns and Best Practices

## Setup Methods

Use `setUp()` for common test initialization:

```php
public function setUp(): void
{
    parent::setUp();

    $this->project = ProjectFactory::new()->create([
        'started_at' => '2024-01-01',
        'ended_at' => '2024-06-01',
    ]);
}
```

## Route Testing Pattern

Store route names in class properties when testing multiple routes:

```php
private string $routeName = 'timeline-event.store';

#[Test]
public function itCreatesResource(): void
{
    // Given
    $user = $this->createUserWithPermission();

    // When
    $response = $this->actingAs($user)
        ->postJson(route($this->routeName), $this->validPayload());

    // Then
    $response->assertRedirect();
}
```

## Best Practices

### 1. One Assertion Per Concept

Each test should verify one specific behavior, but can have multiple related assertions:

```php
// Good - Single concept, multiple related assertions
#[Test]
public function itCreatesTimelineEventWithAllFields(): void
{
    // ...
    $this->assertDatabaseHas('timeline_events', [
        'date' => '2024-01-02',
        'title' => 'Event title',
        'venue' => 'Venue name',
    ]);
}

// Bad - Testing multiple unrelated concepts
#[Test]
public function itCreatesAndUpdatesAndDeletesEvent(): void
{
    // Don't do this
}
```

### 2. Use Factories Over Direct Model Creation

```php
// Good
$artist = ArtistFactory::new()->create(['name' => 'Test Artist']);

// Avoid
$artist = Artist::create(['name' => 'Test Artist', /* many fields */]);
```

### 3. Test Both Positive and Negative Cases

```php
#[Test]
public function userWithPermissionCanAccessResource(): void { }

#[Test]
public function userWithoutPermissionCannotAccessResource(): void { }
```

### 4. Keep Tests Isolated

- Don't depend on other tests
- Don't depend on test execution order
- Use `setUp()` for common initialization
- Database transactions ensure each test starts fresh

### 5. Use Descriptive Test Names

```php
// Good
#[Test]
public function itValidatesEmailFormatWhenCreatingUser(): void

// Bad
#[Test]
public function test1(): void
```

### 6. Avoid Testing Framework Features

Don't test Laravel's built-in functionality - test YOUR code:

```php
// Bad - Testing Laravel's routing
#[Test]
public function routeExists(): void
{
    $this->assertTrue(Route::has('resource.index'));
}

// Good - Testing your controller logic
#[Test]
public function itReturnsResourcesForAuthenticatedUser(): void
{
    // ...
}
```

### 7. Use Type Hints

Always type-hint return types and parameters:

```php
#[Test]
public function itDoesSomething(): void  // Always void for tests
{
    // ...
}

private function createUser(): User
{
    return UserFactory::new()->create();
}
```

### 8. Clean Up After Yourself

Database transactions handle this automatically, but for Storage/Http:

```php
public function setUp(): void
{
    parent::setUp();
    Storage::fake('s3');  // Fake storage creates temp directory that's auto-cleaned
    Http::fake();         // Fake HTTP prevents real requests
}
```

### 9. Don't Assert Too Much

Keep assertions focused on the behavior being tested:

```php
// Good - Testing the specific behavior
#[Test]
public function itMarksEventAsAnnounced(): void
{
    // ...
    $this->assertTrue($event->is_announced);
}

// Bad - Testing everything
#[Test]
public function itMarksEventAsAnnounced(): void
{
    // ...
    $this->assertTrue($event->is_announced);
    $this->assertEquals('Event', $event->title);        // Not relevant
    $this->assertNotNull($event->created_at);          // Not relevant
    $this->assertInstanceOf(TimelineEvent::class, $event); // Obvious
}
```

### 10. Test Edge Cases

```php
#[Test]
public function itHandlesEmptyResults(): void

#[Test]
public function itHandlesNullValues(): void

#[Test]
public function itHandlesMaximumLength(): void
```

## Common Gotchas

### 1. Refreshing Models

After database changes, use `fresh()` or `refresh()`:

```php
$artist = ArtistFactory::new()->create();

// Make changes via observer or another process
$platformProfile->delete();

// Get fresh instance from database
$this->assertNull($artist->fresh()->spotify_artist_id);
```

### 2. Queue Faking

When using `Queue::fake()`, jobs are not actually executed:

```php
Queue::fake();

// Job is queued but NOT executed
ProcessMediaJob::dispatch($media);

// Assert it was queued
Queue::assertPushed(ProcessMediaJob::class);

// But media is NOT actually processed!
```

### 3. UUIDs in Tests

Control UUID generation for deterministic tests:

```php
use Illuminate\Support\Str;

$uuid = Str::uuid();
Str::createUuidsUsing(fn () => $uuid);

// All UUID generations will now return $uuid

// In tearDown:
Str::createUuidsNormally();
```

### 4. HTTP Faking Order

Set up Http::fake() BEFORE making requests:

```php
// Good
Http::fake(['*' => Http::response(['data' => 'value'])]);
$response = Http::get('https://api.example.com');

// Bad - fake is set up after request
$response = Http::get('https://api.example.com');
Http::fake();  // Too late!
```

### 5. Time Testing

The base TestCase typically sets `Carbon::setTestNow(now())` in `setUp()`, so all tests have consistent time unless you override it:

```php
use Carbon\Carbon;

#[Test]
public function itUsesCorrectTimestamp(): void
{
    // Given
    Carbon::setTestNow('2024-01-01 12:00:00');

    // When
    $event = TimelineEventFactory::new()->create();

    // Then
    $this->assertEquals('2024-01-01 12:00:00', $event->created_at);
}
```
