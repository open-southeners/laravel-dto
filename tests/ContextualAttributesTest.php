<?php

namespace OpenSoutheners\LaravelDataMapper\Tests;

use OpenSoutheners\LaravelDataMapper\Attributes\Authenticated;
use OpenSoutheners\LaravelDataMapper\Attributes\Inject;
use OpenSoutheners\LaravelDataMapper\Exceptions\UnresolvableContextualAttributeException;
use Workbench\App\Models\User;
use Workbench\Database\Factories\UserFactory;

use function OpenSoutheners\LaravelDataMapper\map;

class ContextualAttributesTest extends TestCase
{
    public function test_authenticated_attribute_resolves_current_user_without_crashing()
    {
        $user = UserFactory::new()->create();

        $this->actingAs($user);

        $dto = map(['title' => 'Hello'])->to(AuthenticatedAuthorFixtureDto::class);

        $this->assertSame('Hello', $dto->title);
        $this->assertInstanceOf(User::class, $dto->author);
        $this->assertTrue($user->is($dto->author));
    }

    public function test_authenticated_attribute_resolves_null_when_unauthenticated()
    {
        $dto = map(['title' => 'Hello'])->to(AuthenticatedAuthorFixtureDto::class);

        $this->assertSame('Hello', $dto->title);
        $this->assertNull($dto->author);
    }

    public function test_inject_attribute_resolves_service_from_container_and_wins_over_input_data()
    {
        $dto = map(['title' => 'Hello', 'sanitizer' => 'ignored-string-value'])
            ->to(InjectedSanitizerFixtureDto::class);

        $this->assertSame('Hello', $dto->title);
        $this->assertInstanceOf(ContextualAttributeFixtureSanitizer::class, $dto->sanitizer);
    }

    public function test_non_promoted_property_with_contextual_attribute_throws_clear_exception()
    {
        $this->expectException(UnresolvableContextualAttributeException::class);
        $this->expectExceptionMessageMatches('/author/');

        map(['title' => 'Hello'])->to(NonPromotedAuthenticatedFixtureDto::class);
    }

    // Subclassing a built-in mapper (proving un-finaling works end-to-end) is
    // covered by MapperRegistryTest::test_subclass_of_built_in_mapper_registered_with_higher_priority_wins().
}

/**
 * Fixture service resolved through `#[Inject]` — proves the contextual
 * attribute value wins over a same-named key present in the input data.
 */
final class ContextualAttributeFixtureSanitizer
{
    //
}

final class AuthenticatedAuthorFixtureDto
{
    public function __construct(
        public string $title,
        #[Authenticated] public ?User $author = null,
    ) {
        //
    }
}

final class InjectedSanitizerFixtureDto
{
    public function __construct(
        public string $title,
        #[Inject(ContextualAttributeFixtureSanitizer::class)] public ContextualAttributeFixtureSanitizer $sanitizer,
    ) {
        //
    }
}

/**
 * `$author` is a plain public property (not constructor-promoted) with no
 * matching constructor parameter, so resolving its contextual attribute
 * can't produce a `ReflectionParameter` to satisfy Laravel 13+'s
 * `resolveFromAttribute()` signature.
 */
final class NonPromotedAuthenticatedFixtureDto
{
    #[Authenticated]
    public ?User $author = null;

    public function __construct(public string $title)
    {
        //
    }
}
