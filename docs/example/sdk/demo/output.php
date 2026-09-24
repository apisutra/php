<?php

declare(strict_types=1);

namespace Example\Records\Demo;

use Example\Records\Resources\Records\Get\Dto\DocumentAttachmentDto;
use Example\Records\Resources\Records\Get\Dto\ImageAttachmentDto;
use Example\Records\Resources\Records\Get\Dto\TagDto;
use Example\Records\Resources\Records\Get\GetRecordResponseDto;
use ReflectionClass;

/**
 * Обращения к типизированным объектам приложения; сериализацию показывает отдельный toArray().
 *
 * @return array<string, mixed>
 */
function describeRecord(GetRecordResponseDto $record): array
{
    return [
        'id' => $record->id,
        'title' => $record->title,
        'createdAt' => $record->createdAt->format(DATE_ATOM),
        'status' => ['value' => $record->status->value, 'title' => $record->status->title()],
        'author' => [
            'name' => $record->author->displayName,
            'email' => $record->author->contact->email,
            'phone' => $record->author->contact->phone,
            'locale' => $record->author->contact->locale,
        ],
        'tags' => $record->tags->mapToArray(static fn (TagDto $tag): string => $tag->name),
        'attachments' => array_map(describeAttachment(...), $record->attachments),
        'relatedIds' => $record->relatedIds,
        'rating' => $record->rating,
        'readingTimeSeconds' => $record->readingTimeSeconds,
        'externalId' => $record->externalId,
        'featured' => $record->featured,
        'description' => $record->description,
        'updatedAt' => $record->updatedAt,
        'revision' => $record->revision,
        'extra' => $record->_extra,
    ];
}

/** @return array<string, mixed> */
function describeAttachment(ImageAttachmentDto|DocumentAttachmentDto $attachment): array
{
    return [
        'class' => (new ReflectionClass($attachment))->getShortName(),
        'type' => $attachment->type,
        'details' => $attachment instanceof ImageAttachmentDto
            ? ['width' => $attachment->width, 'height' => $attachment->height]
            : ['pages' => $attachment->pages, 'preview' => $attachment->preview->content()],
        'extra' => $attachment->_extra,
    ];
}

/** @return array<string, mixed> */
function describeDefaults(GetRecordResponseDto $fallback, GetRecordResponseDto $nullTitle): array
{
    return [
        'fallbackId' => $fallback->id,
        'missingTitle' => $fallback->title,
        'nullTitle' => $nullTitle->title,
        'tagCount' => $fallback->tags->count(),
        'description' => $fallback->description,
        'updatedAt' => $fallback->updatedAt,
        'revision' => $fallback->revision,
        'explicitAuthorName' => $fallback->author->displayName,
    ];
}
