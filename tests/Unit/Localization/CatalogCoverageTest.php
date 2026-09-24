<?php

declare(strict_types=1);

use ApiSutra\Localization\MessageCatalog;
use ApiSutra\Localization\MessageFormatter;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

it('связывает декларации сообщений ядра с каталогом и обязательными параметрами', function (): void {
    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $finder = new NodeFinder();
    $catalog = MessageCatalog::all('en');
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/src')) as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        foreach ($finder->findInstanceOf($parser->parse(file_get_contents($file->getPathname())), New_::class) as $node) {
            if (!$node->class instanceof Name || $node->class->toString() !== 'Message' || !$node->args[0]->value instanceof String_) {
                continue;
            }
            $key = $node->args[0]->value->value;
            expect($catalog, $file->getPathname() . ':' . $node->getStartLine())->toHaveKey($key);
            $parameters = $node->args[1]->value ?? null;
            if ($parameters === null || $parameters instanceof Array_) {
                $provided = [];
                foreach ($parameters?->items ?? [] as $item) {
                    if ($item?->key instanceof String_) {
                        $provided[] = $item->key->value;
                    }
                }
                expect(array_diff(MessageFormatter::parameters($catalog[$key]), $provided), $key)->toBe([]);
            }
        }
    }
});
