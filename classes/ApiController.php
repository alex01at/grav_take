<?php

declare(strict_types=1);

namespace Grav\Plugin\Take;

use Grav\Plugin\Api\Controllers\AbstractApiController;
use Grav\Plugin\Api\Exceptions\NotFoundException;
use Grav\Plugin\Api\Response\ApiResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * admin2-Backend für die Seite /plugin/take (Feiern + Eintragungen).
 */
final class ApiController extends AbstractApiController
{
    private const PERMISSION = 'api.take';

    public function events(ServerRequestInterface $request): ResponseInterface
    {
        $this->requirePermission($request, self::PERMISSION);

        return ApiResponse::create(['events' => EntryStore::allGrouped()]);
    }

    public function badge(ServerRequestInterface $request): ResponseInterface
    {
        $this->requirePermission($request, self::PERMISSION);

        $count = 0;
        foreach (EntryStore::allGrouped() as $event) {
            $count += count($event['entries']);
        }

        return ApiResponse::create(['count' => $count]);
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $this->requirePermission($request, self::PERMISSION);

        $eventKey = (string) $this->getRouteParam($request, 'eventKey');
        $id = (string) $this->getRouteParam($request, 'id');

        $body = $request->getParsedBody();
        if (!is_array($body) || !$body) {
            $body = json_decode((string) $request->getBody(), true) ?: [];
        }

        $ok = EntryStore::update($eventKey, $id, (string) ($body['name'] ?? ''), (array) ($body['items'] ?? []));
        if (!$ok) {
            throw new NotFoundException("Eintrag '{$id}' nicht gefunden.");
        }

        return ApiResponse::create(['updated' => $id]);
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $this->requirePermission($request, self::PERMISSION);

        $eventKey = (string) $this->getRouteParam($request, 'eventKey');
        $id = (string) $this->getRouteParam($request, 'id');

        if (!EntryStore::delete($eventKey, $id)) {
            throw new NotFoundException("Eintrag '{$id}' nicht gefunden.");
        }

        return ApiResponse::create(['deleted' => $id]);
    }
}
