<?php

namespace Drupal\webform_modal_composite\Service;

use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\WebformSubmissionForm;
use Symfony\Component\HttpFoundation\Request;

class WebformModalService
{
    /**
     * Renderiza un webform
     *
     * @return array
     */
    public function loaderWebform(): array
    {
        $build = [
            'container' => [
                '#type' => 'container',
                '#attributes' => [
                    'class' => ['notices'],
                ],
                'content' => [
                    '#markup' => t('Loader!!'),
                ]
            ]
        ];

        return $build;
    }

    /**
     * Renderiza un webform
     *
     * @param Request $request
     * @param string $name
     * @return array
     */
    public function renderWebform(Request $request, string $name): array
    {
        $webform = Webform::load($name);

        if ($webform && WebformSubmissionForm::isOpen($webform)) {
            $info = $this->getDataRequest($request);

            $build = [
                '#type' => 'webform',
                '#webform' => $name,
                '#information' => false,
                '#default_data' => $info->getData(),
            ];
        } else {
            $build = [
                '#markup' => t('Webform Not Found!!'),
            ];
        }

        return $build;
    }

    /**
     * Procesa el envío de un formulario
     *
     * @param Request $request
     * @param string $name
     * @return array
     */
    public function processWebform(Request $request, string $name): array
    {
        $data = [
            'ok' => true,
        ];

        $webform = Webform::load($name);

        if ($webform && WebformSubmissionForm::isOpen($webform)) {
            $dataForm = \array_diff_key($request->request->all(), \array_fill_keys([
                'form_build_id',
                'form_token',
                'form_id',
                'sid',
                'webformModalFields',
                'webformModalSaveSubmission',
            ], true));

            $values = [
                'webform_id' => $webform->id(),
                'entity_type' => null,
                'entity_id' => null,
                'in_draft' => false,
                'token' => $request->request->get('form_token', ''),
                'data' => $dataForm,
            ];

            $errors = WebformSubmissionForm::validateFormValues($values);

            if (!empty($errors)) {
                $data['ok'] = false;
                $data['errors'] = \array_map(fn($error) => (string) $error, $errors);
            } else {
                if ($request->request->get('sid')) {
                    $webformSubmission = WebformSubmission::load($request->request->get('sid'));

                    if (!$webformSubmission) {
                        throw new \Exception(\sprintf('No se ha encontrado un submission con el id %d', $request->request->get('sid')));
                    }

                    foreach ($dataForm as $field => $value) {
                        $webformSubmission->setElementData($field, $value);
                    }
                } else {
                    $webformSubmission = WebformSubmission::create($values);
                }

                $data += $this->getData($webform, $webformSubmission, $request);
            }
        }

        return $data;
    }

    /**
     * Genera los datos de la respuesta
     *
     * @param WebformInterface $webform
     * @param WebformSubmissionInterface $webformSubmission
     * @param Request $request
     * @return array
     */
    private function getData(WebformInterface $webform, WebformSubmissionInterface $webformSubmission, Request $request): array
    {
        $dataNormalized = [];
        $data = $webformSubmission->getData();
        $fields = @\json_decode($request->request->get('webformModalFields', ''), true) ?? [];

        foreach ($fields as $setting) {
            if (!\array_key_exists($setting['key'], $data)) {
                continue;
            }

            $dataNormalized[$setting['key']] = $this->getValue($data, $setting);
        }

        $data = [
            'data' => $data,
            'fields' => $dataNormalized,
            'fields' => $dataNormalized,
            'saveSubmission' => $request->request->get('webformModalSaveSubmission', false),
            'sid' => $request->request->get('sid'),
        ];

        return $data;
    }

    /**
     * Obtiene los datos del formulario de la petición
     *
     * @param Request $request
     * @return object
     */
    private function getDataRequest(Request $request): object
    {
        $data = $request->request->all('data');
        $sid = $request->request->get('sid');

        if (!\is_array($data)) {
            $data = [];
        }

        return new class($data, $sid) {
            public function __construct(private array $data, private ?int $sid) {}

            public function getData(): array
            {
                return $this->data;
            }

            public function getSid(): ?int
            {
                return $this->sid;
            }
        };
    }

    /**
     * Retorna el dato normalizado según la configuración del campo
     *
     * @param array $data
     * @param array $setting
     * @return null|boolean|string|integer|float
     */
    private function getValue(array $data, array $setting): null|bool|string|int|float
    {
        return $data[$setting['key']];
    }
}
