<?php

namespace OpenSoutheners\LaravelDataMapper\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Workbench\App\DataTransferObjects\PostSummaryData;
use Workbench\App\DataTransferObjects\SerializablePostData;
use Workbench\App\DataTransferObjects\SerializablePostWrapperData;
use Workbench\App\Enums\PostStatus;
use Workbench\App\Jobs\SummarisePostJob;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Database\Factories\PostFactory;
use Workbench\Database\Factories\UserFactory;

use function OpenSoutheners\LaravelDataMapper\map;

class SerializesMappingTest extends TestCase
{
    protected function makeDto(?Post $post = null, ?Collection $reviewers = null): SerializablePostData
    {
        return map([
            'title' => 'Weekly roundup',
            'status' => PostStatus::Hidden->value,
            'publishedAt' => Carbon::parse('2026-01-15 10:30:00')->timestamp,
            'post' => $post?->id,
            'reviewers' => ($reviewers ?? Collection::make())->pluck('id')->all(),
            'note' => 'internal note',
        ])->to(SerializablePostData::class);
    }

    public function test_serialize_payload_contains_no_model_attribute_data()
    {
        $post = PostFactory::new()->create();
        $reviewer = UserFactory::new()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

        $dto = $this->makeDto($post, Collection::make([$reviewer]));

        $payload = serialize($dto);

        $this->assertStringNotContainsString('Ada Lovelace', $payload);
        $this->assertStringNotContainsString('ada@example.com', $payload);
        $this->assertStringNotContainsString($post->title, $payload);
        $this->assertStringNotContainsString($post->slug, $payload);

        // Only the collapsed keys should be present, not the full rows.
        $this->assertStringContainsString((string) $post->id, $payload);
        $this->assertStringContainsString((string) $reviewer->id, $payload);
    }

    public function test_serialize_payload_is_more_compact_than_embedding_full_model_rows()
    {
        $post = PostFactory::new()->create();
        $reviewers = UserFactory::new()->count(3)->create();

        $dto = $this->makeDto($post, $reviewers);

        $traitPayload = serialize($dto);

        // Same Model/Collection/enum/Carbon properties, but on a DTO that
        // doesn't use the trait: native serialize() walks straight into the
        // Model/Collection properties and embeds their full attribute arrays,
        // which is exactly what this trait exists to avoid.
        $nonTraitDto = new PostSummaryData($dto->status, $dto->publishedAt, $post, $reviewers);

        $nonTraitPayload = serialize($nonTraitDto);

        $this->assertLessThan(strlen($nonTraitPayload), strlen($traitPayload));
    }

    public function test_unserialize_rebuilds_model_and_collection_properties_via_fresh_queries()
    {
        $post = PostFactory::new()->create();
        $reviewers = UserFactory::new()->count(2)->create();

        $dto = $this->makeDto($post, $reviewers);

        $payload = serialize($dto);

        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();

        /** @var SerializablePostData $restored */
        $restored = unserialize($payload);

        // One query to re-query the `post` property, one `whereIn` to
        // re-query the whole `reviewers` collection in a single round trip.
        $this->assertCount(2, DB::connection()->getQueryLog());

        $this->assertNotSame($post, $restored->post);
        $this->assertTrue($restored->post->is($post));

        $this->assertSame(
            $reviewers->pluck('id')->sort()->values()->all(),
            $restored->reviewers->pluck('id')->sort()->values()->all()
        );
        $this->assertContainsOnlyInstancesOf(User::class, $restored->reviewers);

        $this->assertSame($dto->status, $restored->status);
        $this->assertSame($dto->publishedAt->toIso8601String(), $restored->publishedAt->toIso8601String());
        $this->assertSame($dto->title, $restored->title);
        $this->assertSame($dto->note, $restored->note);
    }

    public function test_unserialize_of_deleted_model_yields_null_property()
    {
        $post = PostFactory::new()->create();

        $dto = $this->makeDto($post);

        $payload = serialize($dto);

        $post->delete();

        // Pinned behaviour: a Model property whose key no longer resolves to a
        // row comes back `null` on unserialize (the same outcome `map($id)
        // ->to(Model::class)` already produces for any missing id) rather than
        // throwing - the property must be declared nullable for this to work,
        // exactly as it would for any other mapping of a stale/missing id.
        $restored = unserialize($payload);

        $this->assertNull($restored->post);
    }

    public function test_nested_serializes_mapping_dto_round_trips()
    {
        $post = PostFactory::new()->create();
        $reviewers = UserFactory::new()->count(2)->create();

        $summary = $this->makeDto($post, $reviewers);
        $wrapper = new SerializablePostWrapperData('Weekly digest', $summary);

        $payload = serialize($wrapper);

        $this->assertStringNotContainsString($post->title, $payload);

        /** @var SerializablePostWrapperData $restored */
        $restored = unserialize($payload);

        $this->assertSame('Weekly digest', $restored->label);
        $this->assertInstanceOf(SerializablePostData::class, $restored->summary);
        $this->assertTrue($restored->summary->post->is($post));
        $this->assertSame(
            $reviewers->pluck('id')->sort()->values()->all(),
            $restored->summary->reviewers->pluck('id')->sort()->values()->all()
        );
        $this->assertSame($summary->status, $restored->summary->status);
    }

    public function test_queued_job_payload_round_trips_without_leaking_model_attributes()
    {
        $post = PostFactory::new()->create();
        $reviewer = UserFactory::new()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

        $dto = $this->makeDto($post, Collection::make([$reviewer]));

        $job = new SummarisePostJob($dto);

        // Native `serialize()`/`unserialize()` is exactly the mechanism
        // Laravel's queue workers use to move a job's payload across the wire;
        // exercising it directly here avoids depending on a queue connection
        // being configured for the workbench app.
        $payload = serialize($job);

        $this->assertStringNotContainsString('Grace Hopper', $payload);
        $this->assertStringNotContainsString('grace@example.com', $payload);

        SummarisePostJob::$handled = null;

        /** @var SummarisePostJob $restoredJob */
        $restoredJob = unserialize($payload);
        $restoredJob->handle();

        $this->assertNotNull(SummarisePostJob::$handled);
        $this->assertTrue(SummarisePostJob::$handled->post->is($post));
        $this->assertSame($reviewer->id, SummarisePostJob::$handled->reviewers->first()->id);
    }
}
