<?php

namespace Drupal\webform_modal_composite\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\webform_modal_composite\Service\WebformModalService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Exception;

/**
 * Contralador para gestionar los formularios en los modales
 */
class WebformModalController extends ControllerBase
{
    public function __construct(private WebformModalService $webformModalService)
    {
        $this->webformModalService = $webformModalService;
    }

    public static function create(ContainerInterface $container)
    {
        $webformModalService = $container->get(WebformModalService::class);

        return new static($webformModalService);
    }

    /**
     * @return array
     */
    public function loaderWebform(): array
    {
        return $this->webformModalService->loaderWebform();
    }

    /**
     * @return Response
     */
    public function renderWebform(Request $request, string $name): Response
    {
        return new Response(
            \Drupal::service('renderer')->render(
                $this->webformModalService->renderWebform($request, $name)
            )
        );
    }

    /**
     * @return JsonResponse
     */
    public function processWebform(Request $request, string $name): JsonResponse
    {
        return new JsonResponse($this->webformModalService->processWebform($request, $name));
    }
}
