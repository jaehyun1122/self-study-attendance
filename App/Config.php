<?php

declare(strict_types=1);

namespace App;

final class Config
{
    /**
     * @param array<string, mixed> $values
     */
    private function __construct(private readonly array $values)
    {
    }

    public static function fromFile(string $path): self
    {
        $values = require $path;

        return new self(is_array($values) ? $values : []);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @return array{min: int, max: int}
     */
    public function lengthRange(string $key, int $defaultMin, ?int $defaultMax = null): array
    {
        $defaultMax ??= $defaultMin;
        $value = $this->get($key);

        if (is_array($value)) {
            $minValue = $value['min'] ?? $value[0] ?? $defaultMin;
            $maxValue = $value['max'] ?? $value[1] ?? $defaultMax;
        } elseif (is_numeric($value)) {
            $minValue = $value;
            $maxValue = $value;
        } else {
            $minValue = $defaultMin;
            $maxValue = $defaultMax;
        }

        $min = max(0, (int) $minValue);
        $max = max(0, (int) $maxValue);

        if ($max < $min) {
            [$min, $max] = [$max, $min];
        }

        return ['min' => $min, 'max' => $max];
    }

    public function lengthRequirementText(
        string $subject,
        string $key,
        int $defaultMin,
        ?int $defaultMax = null
    ): string {
        $range = $this->lengthRange($key, $defaultMin, $defaultMax);

        if ($range['min'] === $range['max']) {
            return "{$subject} {$range['min']}자로 입력해주세요.";
        }

        if ($range['min'] < 1) {
            return "{$subject} {$range['max']}자까지 입력할 수 있습니다.";
        }

        return "{$subject} {$range['min']}자 이상 {$range['max']}자까지 입력할 수 있습니다.";
    }

    /**
     * @return array<int|string, mixed>
     */
    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : $default;
    }
}
