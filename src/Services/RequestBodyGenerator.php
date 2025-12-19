<?php

namespace YasinTgh\LaravelPostman\Services;

use Illuminate\Foundation\Http\FormRequest;
use Throwable;

class RequestBodyGenerator
{
    public function generateFromRequest(FormRequest $request, array $requestConfig, string $httpMethod): array
    {
        $bodyType = $this->getRequestBodyType($requestConfig['structure']['requests']['default_body_type'], $httpMethod);
        return [
            'mode' => $bodyType,
            $bodyType => $this->generateBodyContent($request, $bodyType, $requestConfig),
            'options' => $this->getBodyOptions($bodyType)
        ];
    }

    protected function getRequestBodyType(string $defaultBodyType, string $httpMethod): string
    {
        return $httpMethod === 'POST' && $defaultBodyType === 'formdata' ? 'formdata' : 'raw';
    }

    protected function generateBodyContent(FormRequest $request, string $bodyType, array $requestConfig): array|string|null
    {
        try {
            $rules = $request->rules();
        } catch (Throwable $th) {
            $rules = [];
        }

        return match ($bodyType) {
            'raw' => json_encode(
                $this->generateFromRules($rules, $requestConfig),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
            'formdata' => $this->generateFormData($rules, $requestConfig),
            default => json_encode(['message' => 'Success'])
        };
    }

    protected function generateFromRules(array $rules, array $requestConfig): array
    {
        $data = [];

        foreach ($rules as $field => $rule) {
            $this->setNestedValue($data, $field, $rule, $requestConfig);
        }

        return $data;
    }

    protected function setNestedValue(&$data, string $field, array|string|object $rules, array $requestConfig): void
    {
        if (is_object($rules)) {
            return;
        }

        $rules = is_array($rules) ? $rules : explode('|', $rules);
        $value = $this->generateFieldValue($field, $rules, $requestConfig);

        // Handle array notation: field.*.subfield -> field[].subfield
        if (str_contains($field, '.*')) {
            $this->setArrayNestedValue($data, $field, $value);
        } else {
            // Simple field without array notation
            $this->setNestedValueInArray($data, $field, $value);
        }
    }

    protected function generateFieldValue(string $field, array|string $rules, array $requestConfig): mixed
    {
        $rules = is_array($rules) ? $rules : explode('|', $rules);
        $defaultValues = data_get($requestConfig, 'structure.requests.default_values', []);


        if (array_key_exists($field, $defaultValues)) {
            return $defaultValues[$field];
        }

        if (in_array('email', $rules)) {
            return 'user' . rand(1, 100) . '@example.com';
        }

        if (in_array('array', $rules)) {
            return [];
        }

        if (in_array('integer', $rules)) {
            $min = 0;
            $max = 10;
            foreach ($rules as $rule) {
                if (is_string($rule) && str_starts_with($rule, 'min:')) {
                    $min = (int)str_replace('min:', '', $rule);
                }
                if (is_string($rule) && str_starts_with($rule, 'max:')) {
                    $max = (int)str_replace('max:', '', $rule);
                }
            }
            return rand($min, $max);
        }

        if (in_array('numeric', $rules)) {
            $min = 1;
            $max = 100;
            foreach ($rules as $rule) {
                if (is_string($rule) && str_starts_with($rule, 'min:')) {
                    $min = (int)str_replace('min:', '', $rule);
                }
                if (is_string($rule) && str_starts_with($rule, 'max:')) {
                    $max = (int)str_replace('max:', '', $rule);
                }
            }
            return rand($min, $max);
        }

        if (in_array('boolean', $rules)) {
            return rand(0, 1);
        }

        if (in_array('date_format', $rules)) {
            foreach ($rules as $rule) {
                if (is_string($rule) && str_starts_with($rule, 'date_format:')) {
                    $format = str_replace('date_format:', '', $rule);
                    return $this->generateDateTimeValue($format);
                }
            }
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

        return 'sample_text';
    }

    protected function generateDateTimeValue(string $format): string
    {
        return match ($format) {
            'H:i' => date('H:i'),
            'Y-m-d' => date('Y-m-d'),
            'Y-m-d H:i:s' => date('Y-m-d H:i:s'),
            default => date($format)
        };
    }

    protected function setArrayNestedValue(&$data, string $field, mixed $value): void
    {
        // Split by .* to get array levels
        $parts = explode('.*', $field);
        $current = &$data;

        foreach ($parts as $index => $part) {
            if ($index === 0) {
                // First part - root field
                if (!isset($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            } elseif ($index === count($parts) - 1) {
                // Last part - set the value
                $cleanPart = ltrim($part, '.');

                if (empty($current)) {
                    $current[] = [];
                }

                if ($cleanPart) {
                    $this->setNestedValueInArray($current[0], $cleanPart, $value);
                }
            } else {
                // Middle parts - nested arrays
                $cleanPart = ltrim($part, '.');

                if (empty($current)) {
                    $current[] = [];
                }

                if (!isset($current[0][$cleanPart])) {
                    $current[0][$cleanPart] = [];
                }

                $current = &$current[0][$cleanPart];
            }
        }
    }

    protected function setNestedValueInArray(&$target, string $path, mixed $value): void
    {
        $parts = explode('.', $path);

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                // Last part - set the value
                $target[$part] = $value;
            } else {
                // Intermediate part - navigate or create
                if (!isset($target[$part])) {
                    $target[$part] = [];
                }
                $target = &$target[$part];
            }
        }
    }

    protected function generateFormData(array $rules, array $requestConfig): array
    {
        $data = $this->generateFromRules($rules, $requestConfig);
        return $this->flattenForFormData($data);
    }

    protected function flattenForFormData(array $data, string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $newKey = $prefix ? "{$prefix}[{$key}]" : $key;

            if (is_array($value) && !empty($value)) {
                // Check if it's a sequential array (list) or associative
                if ($this->isSequentialArray($value)) {
                    // It's a list - add indices
                    foreach ($value as $index => $item) {
                        $indexedKey = "{$newKey}[{$index}]";
                        if (is_array($item)) {
                            $result = array_merge($result, $this->flattenForFormData($item, $indexedKey));
                        } else {
                            $result[] = [
                                'key' => $indexedKey,
                                'value' => $item,
                                'type' => 'text'
                            ];
                        }
                    }
                } else {
                    // It's associative - recurse without indices
                    $result = array_merge($result, $this->flattenForFormData($value, $newKey));
                }
            } elseif (!is_array($value)) {
                $result[] = [
                    'key' => $newKey,
                    'value' => $value,
                    'type' => 'text'
                ];
            }
        }

        return $result;
    }

    protected function isSequentialArray(array $array): bool
    {
        $keys = array_keys($array);
        return $keys === range(0, count($array) - 1);
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
