<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records\Get;

use ApiSutra\Attributes\Behavior\Retry;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

// HTTP-метод и адрес операции.
#[Get('/records/{id}')]
// Повторы при временных ошибках API: до 3 попыток, включая первую.
#[Retry(attempts: 3)]
// Преобразовать содержимое поля data в типизированный DTO.
#[Returns(GetRecordResponseDto::class, unwrap: 'data')]
final class GetRecordRequest extends AbstractRequest
{
    public function __construct(
        // Подставить id в {id} адреса запроса.
        #[Path]
        public int $id,
    ) {
    }
}
