---
name: laravel-testing
description: Laravel/PHP testing patterns and conventions. Use when writing tests, creating test files, adding test coverage, or when the user asks to "write a test", "test this", "add tests", or discusses PHPUnit, feature tests, unit tests.
allowed-tools: Read, Glob, Grep, Edit, Write, Bash
---

# Laravel Testing Guidelines

Follow these patterns when writing tests for Laravel applications.

## Base Test Setup

Extend the application's base TestCase:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class YourTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Setup code
    }

    #[Test]
    public function itDoesWhatYouExpect(): void
    {
        // Test implementation
    }
}
```

## Test Naming Conventions

Use the `#[Test]` attribute with descriptive camelCase method names:

```php
#[Test]
public function itCreatesAnArtistFromRms(): void

#[Test]
public function userWithPermissionCanCreateTimelineEvent(): void

#[Test]
public function userWithoutPermissionCannotCreateTimelineEvent(): void

#[Test]
public function unauthenticatedUserCannotCreateTimelineEvent(): void
```

**Naming patterns:**
- Positive tests: `it{Does}Something`, `user{Can}DoSomething`
- Negative tests: `it{DoesNot}DoSomething`, `user{Cannot}DoSomething`
- Validation: `it{Validates}Field`, `itFails{When}Condition`
- Authorization: `authenticated{User}CanAccessResource`

## Test Structure (AAA - Given/When/Then Pattern)

Follow **Arrange, Act, Assert** with comment separators in Given When Then format:

```php
#[Test]
public function itCreatesATimelineEventWithRedirectUrl(): void
{
    // Given
    $user = UserFactory::new()->withPermissions([Permission::UPDATE_PROJECT])->create();
    $project = ProjectFactory::new()->create();
    $redirectUrl = route('project.timeline.show', ['project' => $project]);

    // When
    $response = $this->actingAs($user)
        ->postJson(route('timeline-event.store'), [
            'schedulable_ids' => [$project->hash_id],
            'date' => '2024-01-02',
            'title' => 'Event title',
        ]);

    // Then
    $response->assertRedirect($redirectUrl)
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('timeline_events', 1);
}
```

**Comment styles:**
- `// Given` - Setup/arrange
- `// When` - Action
- `// Then` - Assertions
- `// When / Then` - Combined action and assertion

## Test Types

### Feature Tests

Test complete HTTP requests and application flows:

```php
#[Test]
public function authenticatedUserCanViewResource(): void
{
    // Given
    $user = UserFactory::new()->create();
    $resource = ResourceFactory::new()->create();

    // When
    $response = $this->actingAs($user)
        ->getJson(route('resource.show', $resource));

    // Then
    $response->assertOk()
        ->assertJsonStructure(['resource' => ['id', 'name']]);
}
```

### Unit Tests

Test isolated components:

```php
#[Test]
public function itBelongsToAUser(): void
{
    // Given
    $user = UserFactory::new()->create();
    $media = MediaFactory::new()->forUser($user)->create();

    // Then
    $this->assertEquals($user->id, $media->uploadedByUser->id);
}
```

### Integration Tests

Test external service interactions:

```php
#[Test]
public function itParsesTourTimelineEvents(): void
{
    // Given
    $input = <<<'EOF'
        DATE    CITY, ST    VENUE
        Tue 3/19/24    CHICAGO, IL    City Winery
    EOF;

    $this->actingAs(UserFactory::new()->create());

    // When
    $output = (new ParseTimelineEventsWorkflow($input))->run();

    // Then
    $this->assertNotNull($output);
    $this->assertCount(1, $output);
    $this->assertEquals('2024-03-19', $output[0]['date']);
}
```

## Factory Usage

```php
// Create single record
$artist = ArtistFactory::new()->create();

// Create with specific attributes
$artist = ArtistFactory::new()->create([
    'name' => 'Test Artist',
    'spotify_artist_id' => '123abc',
]);

// Create with relationships
$media = MediaFactory::new()->forUser($user)->create();

// Create multiple records
$artists = ArtistFactory::new()->count(5)->create();

// Use factory states
$artist = ArtistFactory::new()->withSpotifyPlatformProfile()->create();
```

## Authentication & Authorization

```php
#[Test]
public function authenticatedUserCanAccessResource(): void
{
    // Given
    $user = UserFactory::new()->create();

    // When / Then
    $this->actingAs($user)
        ->getJson(route('resource.index'))
        ->assertOk();
}

#[Test]
public function unauthenticatedUserCannotAccessResource(): void
{
    // When / Then
    $this->getJson(route('resource.index'))
        ->assertUnauthorized();
}

#[Test]
public function userWithPermissionCanUpdateResource(): void
{
    // Given
    $user = UserFactory::new()->withPermissions([
        Permission::UPDATE_RESOURCE
    ])->create();

    // When / Then
    $this->actingAs($user)
        ->patchJson(route('resource.update', $resource), $data)
        ->assertOk();
}

#[Test]
public function userWithoutPermissionCannotUpdateResource(): void
{
    // Given
    $user = UserFactory::new()->create();

    // When / Then
    $this->actingAs($user)
        ->patchJson(route('resource.update', $resource), $data)
        ->assertForbidden();
}
```

## Helper Methods

Extract common logic into private helpers:

```php
private function createUserWithPermission(): User
{
    return UserFactory::new()->withPermissions([
        Permission::ACCESS_INTERNAL_PORTAL,
        Permission::UPDATE_PROJECT,
    ])->create();
}

private function validPayload(): array
{
    return [
        'title' => 'Event title',
        'date' => '2024-01-02',
    ];
}
```

## Data Providers

Use for testing multiple scenarios:

```php
#[Test, DataProvider('inputProvider')]
public function itValidatesField(\Closure $createPayload, array $expectedErrors): void
{
    // Given
    $payload = $createPayload();

    // When
    $response = $this->actingAs($this->createUserWithPermission())
        ->postJson(route($this->routeName), $payload);

    // Then
    if ($expectedErrors) {
        $response->assertJsonValidationErrors($expectedErrors);
        return;
    }

    $response->assertRedirect();
}

public static function inputProvider(): array
{
    $makeValidInput = function () {
        return [
            'date' => '2024-01-02',
            'title' => 'Event title',
        ];
    };

    return [
        'date is required' => [
            fn () => [...$makeValidInput(), 'date' => null],
            ['date'],
        ],
        'date must be a date' => [
            fn () => [...$makeValidInput(), 'date' => 'invalid-date'],
            ['date'],
        ],
        'valid input passes' => [
            fn () => $makeValidInput(),
            [],
        ],
    ];
}
```

## Inertia Assertions

```php
use Inertia\Testing\AssertableInertia;

$response->assertInertia(function (AssertableInertia $page) {
    $page->component('Resource/Show')
        ->has('resource')
        ->where('resource.id', 1)
        ->count('resources', 10);
});
```

## Testing Observers

Test that model observers fire correctly:

```php
#[Test]
public function artistSpotifyIdIsUpdatedWhenSpotifyProfileIsCreated(): void
{
    // Given
    $artist = ArtistFactory::new()->create();

    // When
    PlatformProfileFactory::new()
        ->for($artist, 'owner')
        ->forPlatform(PlatformProfileType::SPOTIFY)
        ->create(['external_id' => 'new-spotify-id']);

    // Then
    $this->assertEquals('new-spotify-id', $artist->fresh()->spotify_artist_id);
}
```

## Partial Mocking

Use when you need to mock specific methods while keeping others real:

```php
$this->partialMock(ChartmetricService::class, function (MockInterface $mock) {
    $mock->shouldReceive('getChartmetricArtistId')
        ->once()
        ->andReturn(456);

    $mock->shouldReceive('getArtistUrls')
        ->once()
        ->andReturn(['youtube' => 'https://youtube.com/channel/123']);
});
```

## Creating Tests

```bash
# Create a feature test
php artisan make:test StoreResourceTest

# Create a unit test
php artisan make:test ResourceServiceTest --unit
```

## Running Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Run tests matching a name
php artisan test --filter=itDoesWhatYouExpect

# Run tests in a specific class
php artisan test --filter=YourFeatureTest

# Run in parallel
php artisan test --parallel
```

## Additional Resources

- **[patterns.md](patterns.md)** - Best practices and common gotchas

For standard Laravel testing assertions and facade faking (Queue, Storage, Http, Mail, etc.), use Laravel Boost's documentation search.
