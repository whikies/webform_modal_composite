<?php

namespace Drupal\webform_modal_composite\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\webform\Element\WebformCompositeBase;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Utility\WebformElementHelper;
use Drupal\Core\Url;

/**
 * Provides a webform custom composite element.
 *
 * @FormElement("webform_modal_composite")
 */
class WebformModalComposite extends WebformCompositeBase
{
    private static string $selector;
    private static string $selectorUnique;
    private static string $selectorFieldHidden;

    /**
     * {@inheritdoc}
     */
    public static function getCompositeElements(array $element)
    {
        $elements = [];

        if (empty($element['#webform_name'])) {
            return $elements;
        }

        static::$selector = \sprintf('modal-field-composite-%s', \str_replace('_', '-', $element['#webform_key']));
        static::$selectorUnique = \sprintf('%s-%s', static::$selector, static::generateToken());

        static::buildFieldValues($element, $elements);
        static::buildFields($element, $elements);
        static::buildLinkModal($element, $elements);

        return $elements;
    }

    /**
     * Genera el campo values para todos los valores de registro
     *
     * @param array $element
     * @param array $elements
     * @return void
     */
    private static function buildFieldValues(array $element, array &$elements): void
    {
        static::$selectorFieldHidden = \sprintf('%s-webform-values', static::$selectorUnique);

        $elements['webform_modal_values'] = [
            '#type' => 'hidden',
            '#attributes' => [
                'class' => [
                    'modal-field-composite-field-values',
                    static::$selectorFieldHidden
                ]
            ],
        ];
    }

    /**
     * Genera los campos configurados para mostrar
     *
     * @param array $element
     * @param array $elements
     * @return void
     */
    private static function buildFields(array $element, array &$elements): void
    {
        foreach (($element["#webform_fields"] ?? []) as $field) {
            $name = $field['key'];

            if (\in_array($name, [
                'webform_modal_values',
                'webform_modal_link'
            ])) {
                continue;
            }

            unset($field['key']);

            if (empty($field['#attributes'])) {
                $field['#attributes'] = [];
            }

            if (empty($field['#wrapper_attributes'])) {
                $field['#wrapper_attributes'] = [];
            }

            $field['#attributes']['data-values-key'] = $name;

            $field['#attributes']['class'] = [
                'modal-field-composite-field',
                static::$selector,
                static::$selectorUnique,
                \sprintf('%s-%s', static::$selectorUnique, $name)
            ];

            $field['#wrapper_attributes']['class'] = [
                'modal-field-composite-field-wrapper',
            ];

            $elements[$name] = $field;
        }
    }

    /**
     * Genera el botón para mostrar el modal
     *
     * @param array $element
     * @param array $elements
     * @return void
     */
    private static function buildLinkModal(array $element, array &$elements): void
    {
        $elements['webform_modal_link'] = [
            '#title' => $element['#modal_button'] ?? t('Add'),
            '#type' => 'link',
            '#url' => Url::fromRoute('webform_modal_composite.loader'),
            '#attached' => [
                'library' => [
                    'core/drupal.dialog.ajax',
                    'webform_modal_composite/webform_modal_composite',
                    // 'webform_modal_composite/webform_modal_composite_bootstrap',
                ],
            ],
        ];

        if (\Drupal::service('router.admin_context')->isAdminRoute()) {
            static::setButtonOptions($elements, $element, $element['#modal_type_admin'] ?? '');
        } else {
            static::setButtonOptions($elements, $element, $element['#modal_type_front'] ?? '');
        }
    }

    /**
     * Configuras las opciones del botón según el tipo seleccionado
     *
     * @param array $elements
     * @param array $element
     * @param string $type
     * @return void
     */
    private static function setButtonOptions(array &$elements, array $element, string $type): void
    {
        $options = [
            'title' => $element['#modal_title'] ?? '',
            'width' => $element['#modal_width'] ?? 900,
            'webform' => $element['#webform_name'],
            'saveSubmission' => $element['#save_submission'] ?? 0,
            'selectorModalElement' => \sprintf('.%s', static::$selectorUnique),
            'selectorFieldHidden' => \sprintf('.%s', static::$selectorFieldHidden),
            'multiple' => $element['#webform_multiple'] ? 1 : 0,
            'processForm' => (Url::fromRoute('webform_modal_composite.process', [
                'name' => $element['#webform_name']
            ]))->toString(),
            'fieldsForm' => \json_encode($element["#webform_fields"] ?? []),
            'renderForm' => (Url::fromRoute('webform_modal_composite.render', [
                'name' => $element['#webform_name']
            ]))->toString(),
            'classes' => [
                'ui-dialog-content' => 'webform-modal-composite-dialog'
            ],
        ];

        switch ($type) {
            case 'bootstrap':
                $elements['webform_modal_link']['#attributes'] = [
                    'class' => [
                        'webform-modal-composite-buttom',
                        'button',
                        'modal-field-composite-webform-modal-link'
                    ],
                    'data-settings' => \json_encode($options),
                    'style' => 'margin: 0px;'
                ];
                break;

            default:
                $elements['webform_modal_link']['#attributes'] = [
                    'class' => [
                        'use-ajax',
                        'button',
                        'modal-field-composite-webform-modal-link'
                    ],
                    'data-dialog-type' => 'modal',
                    'data-dialog-options' => \json_encode($options),
                    'style' => 'margin: 0px;'
                ];
                break;
        }
    }

    /**
     * Genera un token único para el campo
     *
     * @return string
     */
    private static function generateToken(): string
    {
        return \rtrim(\strtr(\base64_encode(\random_bytes(32)), '+/', '-_'), '=');
    }
}
