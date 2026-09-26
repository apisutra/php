<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Input;

use ApiSutra\Serialization\Rules\InputShape;

/** @internal Структурный проход только по JSON, уже проверенному штатным decoder. */
final readonly class JsonShapeReader
{
    public function read(string $json): ?SourceShapeMap
    {
        $offset = 0;
        return $this->value($json, $offset);
    }

    private function value(string $json, int &$offset): ?SourceShapeMap
    {
        $offset += strspn($json, " \t\r\n", $offset);
        $token = $json[$offset];
        if ($token === '"') {
            $this->skipString($json, $offset);
            return null;
        }
        if ($token !== '{' && $token !== '[') {
            $offset += strcspn($json, ",]} \t\r\n", $offset);
            return null;
        }

        $object = $token === '{';
        $end = $object ? '}' : ']';
        $offset++;
        $children = [];
        $index = 0;
        while (true) {
            $offset += strspn($json, " \t\r\n", $offset);
            if ($json[$offset] === $end) {
                $offset++;
                return new SourceShapeMap($object ? InputShape::Object : InputShape::List, $children);
            }
            $key = $index++;
            if ($object) {
                $start = $offset;
                $this->skipString($json, $offset);
                $key = json_decode(substr($json, $start, $offset - $start), true, 512, JSON_THROW_ON_ERROR);
                $offset += strspn($json, " \t\r\n", $offset) + 1;
            }
            $child = $this->value($json, $offset);
            if ($child === null) {
                // Последний duplicate key мог заменить контейнер скаляром.
                unset($children[$key]);
            } else {
                $children[$key] = $child;
            }
            $offset += strspn($json, " \t\r\n", $offset);
            if ($json[$offset] === ',') {
                $offset++;
            }
        }
    }

    private function skipString(string $json, int &$offset): void
    {
        $offset++;
        while (true) {
            $offset += strcspn($json, "\"\\", $offset);
            if ($json[$offset] === '"') {
                $offset++;
                return;
            }
            $offset += 2;
        }
    }
}
