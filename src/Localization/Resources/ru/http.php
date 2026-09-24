<?php

declare(strict_types=1);

return [
    'http.content_length_does_not_match_the_http_body_size' => 'Content-Length не совпадает с размером HTTP-тела',
    'http.credentials_cannot_be_sent_to_the_target_origin' => 'Передача credentials на целевой origin не разрешена',
    'http.does_not_support_request_destination_isolation' => '{value0} не поддерживает изоляцию назначения запроса',
    'http.http_body_cannot_contain_both_a_string_and_a' => 'HTTP-тело не может одновременно содержать строку и поток',
    'http.invalid_absolute_url_an_encoded_http_https_url_without' => 'Некорректный полный URL; требуется готовый закодированный HTTP/HTTPS адрес без userinfo',
    'http.invalid_ipv6_origin' => 'Некорректный IPv6 origin',
    'http.invalid_origin_port' => 'Некорректный порт origin',
    'http.invalid_request_content_length_transfer_encoding' => 'Некорректные Content-Length/Transfer-Encoding запроса',
    'http.request_destination_or_absolute_url_changed_after_preparation' => 'Назначение или готовый URL изменён после подготовки запроса',
    'http.url_must_contain_an_http_https_origin_with_an' => 'URL должен содержать HTTP/HTTPS origin с ASCII host',
];
