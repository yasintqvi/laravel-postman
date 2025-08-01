<?php

namespace YasinTgh\LaravelPostman\Services;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Throwable;

class RequestBodyGenerator
{
    public function generateFromRequest(FormRequest $request, array $requestConfig, string $httpMethod): array
    {
        $bodyType = $this->getRequestBodyType($requestConfig['default_body_type'], $httpMethod);

        return [
            'mode' => $bodyType,
            $bodyType => $this->generateBodyContent($request, $bodyType),
            'options' => $this->getBodyOptions($bodyType)
        ];
    }

    protected function getRequestBodyType(string $defaultBodyType, string $httpMethod): string
    {
        return $httpMethod === 'POST' && $defaultBodyType === 'formdata' ? 'formdata' : 'raw';
    }

    protected function generateBodyContent(FormRequest $request, string $bodyType): array|string|null
    {
        try {
            $rules = $request->rules();
        } catch (Throwable $th) {
            $rules = [];
        }
        return match ($bodyType) {
            'raw' => json_encode(
                $this->generateFromRules($rules),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
            'formdata' => $this->generateFormData($rules),
            default => json_encode(['message' => 'Success'])
        };
    }

    protected function generateFromRules(array $rules): array
    {
        return collect($rules)->mapWithKeys(function ($rule, $field) use ($rules) {

            if (str_contains($field, '.*')) {

                $field = str_replace('.*', '', $field);

                if (!in_array($field, array_keys($rules))) {
                    return [$field => $this->generateFieldValue($field, ['array'])];
                }
                return [];
            }

            return [$field => $this->generateFieldValue($field, $rule)];
        })->toArray();
    }

    protected function generateFormData(array $rules): array
    {
        return collect($rules)->map(function ($rule, $field) use ($rules) {
            if (str_contains($field, '.*')) {

                $field = str_replace('.*', '', $field);

                if (!in_array($field, array_keys($rules))) {
                    return [
                        'key' => $field,
                        'value' => $this->generateFieldValue($field, ['array']),
                        'type' => 'text'
                    ];
                }
                return [];
            }

            return [
                'key' => $field,
                'value' => $this->generateFieldValue($field, $rule),
                'type' => in_array('file', $rule) ? 'file' : 'text'
            ];
        })->values()->toArray();
    }

    protected function generateFieldValue(string $field, array|string $rules): mixed
    {
        $rules = is_array($rules) ? $rules : explode('|', $rules);

        if (in_array('email', $rules)) {
            return 'user' . rand(1, 100) . '@example.com';
        }

        if (in_array('array', $rules)) {
            return [];
        }

        if (in_array('numeric', $rules)) {
            $min = 1;
            $max = 100;
            foreach ($rules as $rule) {
                if (is_string($rule) && str_starts_with($rule, 'min:')) $min = (int)str_replace('min:', '', $rule);
                if (is_string($rule) && str_starts_with($rule, 'max:')) $max = (int)str_replace('max:', '', $rule);
            }
            return  rand($min, $max);
        }

        if (in_array('boolean', $rules)) {
            return rand(0, 1) === 1;
        }

        $minLength = 5;

        foreach ($rules as $rule) {

            if ($rule instanceof ValidationRule || $rule instanceof Rule) {
                continue;
            }

            if (is_string($rule) && str_starts_with($rule, 'min:')) {
                $minLength = max($minLength, (int)str_replace('min:', '', $rule));
            }
        }

        return "{$field} sample value";
    }

    protected function getBodyOptions(string $bodyType): array
    {
        return match ($bodyType) {
            'json' => ['raw' => ['language' => 'json']],
            'formdata' => [],
            default => []
        };
    }
}
