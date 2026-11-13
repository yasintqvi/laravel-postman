<?php

namespace YasinTgh\LaravelPostman\Tests\Unit;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use YasinTgh\LaravelPostman\Services\RequestBodyGenerator;
use YasinTgh\LaravelPostman\Tests\Fixtures\EmptyRequest;
use YasinTgh\LaravelPostman\Tests\Fixtures\Status;
use YasinTgh\LaravelPostman\Tests\TestCase;

class RequestBodyGeneratorTest extends TestCase
{
    private function generate(array $rules): array
    {
        $generator = new class extends RequestBodyGenerator {
            public function fromRules(array $rules): array
            {
                return $this->generateFromRules($rules, ['structure' => ['requests' => ['default_values' => []]]]);
            }
        };

        return $generator->fromRules($rules);
    }

    public function test_wildcard_rules_produce_list_elements(): void
    {
        $body = $this->generate([
            'tags' => 'array',
            'tags.*' => 'string',
            'items.*.name' => 'string',
            'matrix.*.*' => 'integer|size:1',
        ]);

        $this->assertSame(['sample_text'], $body['tags']);
        $this->assertSame([['name' => 'sample_text']], $body['items']);
        $this->assertSame([[1]], $body['matrix']);
    }

    public function test_date_format_rule_produces_a_matching_date(): void
    {
        $body = $this->generate(['born' => 'required|date_format:Y-m-d']);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $body['born']);
    }

    public function test_single_rule_objects_and_allowed_values(): void
    {
        $body = $this->generate([
            'status' => new Enum(Status::class),
            'role' => Rule::in(['admin', 'user']),
            'size' => 'required|in:small,large',
            'check' => fn($attribute, $value, $fail) => null,
        ]);

        $this->assertSame(['status' => 'active', 'role' => 'admin', 'size' => 'small', 'check' => 'sample_text'], $body);
    }

    public function test_numbers_respect_range_rules(): void
    {
        foreach (range(1, 20) as $ignored) {
            $body = $this->generate([
                'min_only' => 'integer|min:20',
                'between' => 'integer|between:5,7',
                'exact' => 'numeric|size:42',
            ]);

            $this->assertGreaterThanOrEqual(20, $body['min_only']);
            $this->assertContains($body['between'], [5, 6, 7]);
            $this->assertSame(42, $body['exact']);
        }
    }

    public function test_strings_respect_length_rules(): void
    {
        $body = $this->generate(['long' => 'string|min:20', 'short' => 'string|max:4']);

        $this->assertGreaterThanOrEqual(20, strlen($body['long']));
        $this->assertSame('samp', $body['short']);
    }

    public function test_empty_rules_give_an_empty_json_object(): void
    {
        $body = (new RequestBodyGenerator())->generateFromRequest(
            new EmptyRequest(),
            ['structure' => ['requests' => ['default_body_type' => 'raw']]],
            'POST',
        );

        $this->assertSame('{}', $body['raw']);
    }
}
