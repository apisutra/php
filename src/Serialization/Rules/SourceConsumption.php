<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

/** @internal Дерево потреблённых сегментов; полное чтение поглощает все дочерние чтения. */
final class SourceConsumption
{
    public bool $full = false;
    public bool $projection = false;
    /** @var array<int|string, self> */
    private array $children = [];

    public static function all(): self
    {
        $node = new self();
        $node->full = true;
        return $node;
    }

    /** @param list<int|string> $segments */
    public function mergeAt(array $segments, self $consumed): void
    {
        if ($this->full) {
            return;
        }
        if ($segments !== []) {
            $key = array_shift($segments);
            $this->children[$key] ??= new self();
            $this->children[$key]->mergeAt($segments, $consumed);
            return;
        }
        if ($consumed->full) {
            $this->full = true;
            $this->children = [];
            return;
        }
        $this->projection = $this->projection || $consumed->projection;
        foreach ($consumed->children as $key => $child) {
            $this->children[$key] ??= new self();
            $this->children[$key]->mergeAt([], $child);
        }
    }

    /** @return array{bool, mixed} Первый элемент отличает удалённый узел от исходного пустого контейнера. */
    public function remainder(mixed $source): array
    {
        if ($this->full) {
            return [false, null];
        }
        if ($this->children === [] && !$this->projection) {
            return [true, $source];
        }
        $data = is_object($source) ? get_object_vars($source) : $source;
        if (!is_array($data)) {
            return [true, $source];
        }
        $remaining = [];
        $removed = false;
        foreach ($data as $key => $value) {
            [$keep, $rest] = isset($this->children[$key])
                ? $this->children[$key]->remainder($value)
                : [true, $value];
            if (!$keep) {
                $removed = true;
                continue;
            }
            if ($this->projection) {
                $remaining[] = ['sourceKey' => $key, 'remainder' => $rest];
            } else {
                $remaining[$key] = $rest;
            }
        }
        if ($remaining === [] && ($removed || $this->projection)) {
            return [false, null];
        }
        return [true, $remaining];
    }
}
