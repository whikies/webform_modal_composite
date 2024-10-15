<?php

namespace Drupal\webform_modal_composite\Plugin\WebformElement;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;
use Drupal\webform\WebformSubmissionForm;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_modal_composite\Element\WebformModalComposite as ElementWebformModalComposite;

/**
 * Provides a custom composite element.
 *
 * @WebformElement(
 *   id = "webform_modal_composite",
 *   label = @Translation("Webform Modal Composite"),
 *   description = @Translation("Crea un modal para añadir un formulario"),
 *   category = @Translation("Composite elements"),
 *   composite = true,
 *   states_wrapper = true,
 * )
 */
class WebformModalComposite extends WebformCompositeBase
{
    private ?string $key = null;
    private bool $starting = false;
    private array $returnFields = [];
    private array $modalSubmissions = [];

    /**
     * Get composite's managed file elements.
     *
     * @param array $element
     *   A composite element.
     *
     * @return array
     *   An array of managed file element keys.
     */
    public function getManagedFiles(array $element)
    {
        $this->key = $element["#webform_key"];
        return parent::getManagedFiles($element);
    }

    /**
     * {@inheritdoc}
     */
    public function preview()
    {
        return [
            '#markup' => '<p>Previsualización no disponible</p>',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function form(array $form, FormStateInterface $form_state)
    {
        $form = parent::form($form, $form_state);
        $element_properties = $form_state->get('element');

        $form['composite']['webform_name'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Webform name'),
            '#maxlength' => null,
            '#description' => $this->t('Machine name del webform que se quiere visualizar en el modal'),
            '#required' => true,
        ];

        $form['composite']['modal_type_front'] = [
            '#type' => 'select',
            '#title' => $this->t('Tipo de modal'),
            '#maxlength' => null,
            '#description' => $this->t('Para ver el tipo de modal bootstrap es necesario añadir la librería'),
            '#required' => true,
            '#options' => [
                'dialog' => $this->t('Dialog'),
                'bootstrap' => $this->t('Bootstrap'),
            ],
        ];

        $form['composite']['modal_type_admin'] = [
            '#type' => 'select',
            '#title' => $this->t('Tipo de modal (Admin)'),
            '#maxlength' => null,
            '#description' => $this->t('Para ver el tipo de modal bootstrap es necesario añadir la librería'),
            '#required' => true,
            '#options' => [
                'dialog' => $this->t('Dialog'),
                'bootstrap' => $this->t('Bootstrap'),
            ],
        ];

        $form['composite']['modal_title'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Título del modal'),
            '#maxlength' => null,
            '#description' => $this->t('Título que saldrá en la parte alta del modal'),
        ];

        $form['composite']['modal_width'] = [
            '#type' => 'number',
            '#title' => $this->t('Ancho modal (px)'),
            '#maxlength' => null,
            '#description' => $this->t('Ancho del modal que contiene el formulario'),
            '#required' => true,
            '#default_value' => 900,
        ];

        $form['composite']['save_submission'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Save submission'),
            '#description' => $this->t(
                'Marca esta opción si quieres guardar el id del submission con los datos del formulario principal.
                Este id se utilizará para editar el submission.
                En caso contrario se creará un submission cada vez que se envíe el formulario del modal.'
            ),
        ];

        $this->addFormFields($form, $form_state);

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function getCompositeElements()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurationFormProperties(array &$form, FormStateInterface $form_state)
    {
        /*
            Dentro de $element_propeties tiene que estar las claves añadidas anteriormente
            Extramemos las marcadas con 1 y las añadimos al array de confguraciones webform_fields

            Esta configuración siempre la reseteamos al principio y después añadimos los campos marcados con 1
        */
        $this->addFieldSetting($form_state);

        return parent::getConfigurationFormProperties($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function preSave(array &$element, WebformSubmissionInterface $webform_submission)
    {
        $data = $webform_submission->getData();
        $dataModal = $data[$element['#webform_key'] ?? ''];

        if (!empty($dataModal['webform_modal_values'])) {
            $dataModal['webform_modal_values'] = $this->saveSubmissionModalForm($element, $dataModal['webform_modal_values']);
        } elseif (\is_array($dataModal) && !empty($dataModal)) {
            foreach ($dataModal as $key => $dataItem) {
                if (!\is_numeric($key)) {
                    continue;
                }

                $dataItem['webform_modal_values'] = $this->saveSubmissionModalForm($element, $dataItem['webform_modal_values'] ?? '');
                $dataModal[$key] = $dataItem;
            }
        }

        $data[$element['#webform_key']] = $dataModal;
        $webform_submission->setData($data);
    }

    /**
     * {@inheritdoc}
     */
    public function postSave(array &$element, WebformSubmissionInterface $webform_submission, $update = TRUE)
    {
        if (!$this->modalSubmissions) {
            return;
        }

        foreach ($this->modalSubmissions as $object) {
            $data = $object->getData();
            $data ??= [];
            $data['submission_parent_id'] = $webform_submission->id();
            $object->setData($data);
            $object->save();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function postLoad(array &$element, WebformSubmissionInterface $webform_submission)
    {
        $data = $webform_submission->getData();
        $dataModal = $data[$element['#webform_key']] ?? '';

        if (!empty($dataModal['webform_modal_values'])) {
            $dataModal = \array_merge($dataModal, $this->loadFields($element, $dataModal['webform_modal_values']));
        } elseif (\is_array($dataModal) && !empty($dataModal)) {
            foreach ($dataModal as $key => $dataItem) {
                if (!\is_numeric($key)) {
                    continue;
                }

                $dataModal[$key] = \array_merge($dataItem, $this->loadFields($element, $dataItem['webform_modal_values']));
            }
        }

        $data[$element['#webform_key']] = $dataModal;
        $webform_submission->setData($data);
    }

    /**
     * {@inheritdoc}
     */
    protected function defineDefaultProperties()
    {
        $properties =  parent::defineDefaultProperties();

        // Link does not have select menus.
        unset(
            $properties['select2'],
            $properties['chosed'],
            $properties['flexbox'],
        );

        $properties['webform_name'] = null;
        $properties['save_submission'] = null;
        $properties['modal_width'] = 900;
        $properties['modal_title'] = null;
        $properties['modal_type_front'] = 'dialog';
        $properties['modal_type_admin'] = 'dialog';
        $properties['webform_fields'] = [];

        if (!$this->starting && $this->webform) {
            $properties["modal_button"] = null;

            $this->starting = true;
            $element = $this->webform->getElementDecoded($this->key);
            $fields = $this->getNameFields($element ?? []);

            foreach ($fields as $field => $setting) {
                if (!\array_key_exists($field, $properties)) {
                    $properties[$field] = null;
                }
            }
        }

        return $properties;
    }

    /**
     * Set multiple element wrapper.
     *
     * @param array $element
     *   An element.
     */
    protected function prepareMultipleWrapper(array &$element)
    {
        if (empty($element['#multiple']) || !$this->supportsMultipleValues()) {
            return;
        }

        parent::prepareMultipleWrapper($element);

        $element['#table_wrapper_attributes'] = [
            'class' => [
                'modal-field-composite-container'
            ]
        ];

        // Set #header.
        if (!empty($element['#multiple__header'])) {
            $element['#header'] = true;

            // Set #element.
            // We don't need to get the initialized composite elements because
            // they will be initialized, prepared, and finalize by the
            // WebformMultiple (wrapper) element.
            // @see \Drupal\webform\Element\WebformMultiple::processWebformMultiple
            $element['#element'] = [];
            $composite_elements = $element['#webform_composite_elements'] ?? [];

            foreach (Element::children($composite_elements) as $composite_key) {
                $composite_element = $composite_elements[$composite_key];

                switch ($composite_key) {
                    case 'webform_modal_link':
                        $options = \json_decode($composite_element['#attributes']['data-dialog-options'], true);
                        $options['multiple'] = '1';
                        $composite_element['#attributes']['data-dialog-options'] = \json_encode($options);
                        break;
                }

                // Transfer '#{composite_key}_{property}' from main element to composite
                // element.
                foreach ($element as $property_key => $property_value) {
                    if (strpos($property_key, '#' . $composite_key . '__') === 0) {
                        $composite_property_key = str_replace('#' . $composite_key . '__', '#', $property_key);
                        $composite_element[$composite_property_key] = $property_value;
                    }
                }

                $element['#element'][$composite_key] = $composite_element;
            }
        }
    }

    /**
     * Obtiene los valores de los campos seleccionados
     *
     * @param array $element
     * @param string $webformModalValues
     * @return array
     */
    private function loadFields(array $element, string $webformModalValues): array
    {
        if (!($values = @\json_decode($webformModalValues, true))) {
            return [];
        }

        $data = [];

        foreach ($element["#webform_fields"] ?? [] as $field) {
            if (isset($values['data'][$field['key']])) {
                $data[$field['key']] = $values['data'][$field['key']];
            }
        }

        return $data;
    }

    /**
     * Guarda el submission relacioando con el formulario del modal
     *
     * @param array $element
     * @param string $webformModalValues
     * @return string
     */
    private function saveSubmissionModalForm(array $element, string $webformModalValues): string
    {
        if (!($values = @\json_decode($webformModalValues, true))) {
            return '';
        }

        if ($values['saveSubmission'] ?? false) {
            $webformSubmission = null;

            if (!empty($values['sid'])) {
                $webformSubmission = WebformSubmission::load($values['sid']);

                if ($webformSubmission) {
                    foreach ($values['data'] ?? [] as $field => $value) {
                        $webformSubmission->setElementData($field, $value);
                    }

                    $webformSubmission = WebformSubmissionForm::submitWebformSubmission($webformSubmission);
                }
            }

            if (!$webformSubmission) {
                $valuesSubmission = [
                    'webform_id' => $element['#webform_name'],
                    'entity_type' => null,
                    'entity_id' => null,
                    'in_draft' => false,
                    'token' => null,
                    'data' => $values['data'],
                ];

                $webformSubmission = WebformSubmissionForm::submitFormValues($valuesSubmission);
            }

            $values['sid'] = $webformSubmission->id();
            $this->modalSubmissions[] = $webformSubmission;

            return \json_encode($values);
        }
    }

    /**
     * Añade los campos al formulario de todos aquellos campos que se pueden mostrar en el composite
     *
     * @param array $form
     * @param FormStateInterface $form_state
     * @return void
     */
    private function addFormFields(array &$form, FormStateInterface $form_state): void
    {
        $fields = $this->getNameFields($form_state->get('element') ?? []);

        if ($fields) {
            $form['composite']['modal_button'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Modal button'),
                '#maxlength' => null,
                '#required' => true,
                '#attributes' => ['autofocus' => 'autofocus'],
                '#default_value' => $this->t('Add')
            ];

            $form['composite']['fields'] = [
                '#type' => 'details',
                '#title' => $this->t('Selecciona los campos que quieres mostrar'),
            ];
        }

        foreach ($fields as $field => $setting) {
            $form['composite']['fields'][$field] = [
                '#type' => 'checkbox',
                '#title' => $setting['#title'],
            ];
        }
    }

    /**
     * Obtiene todos los campos que estan disponibles para mostrarlos en el listado del composite como disabled
     *
     * @param FormStateInterface $form_state
     * @return array
     */
    private function getNameFields(array $element): array
    {
        static $fields = [];

        if ($fields) {
            return $fields;
        }

        if (empty($element["#webform_name"])) {
            return $fields;
        }

        $webform = Webform::load($element['#webform_name']);

        if (!$webform) {
            return $fields;
        }

        return $this->filterFields($webform->getElementsDecoded(), null);
    }

    /**
     * Obtenemos las condiciones de usuarios establecidas a cada campo
     * @param array $elements
     * @param string|null $name
     * @return array
     */
    private function filterFields(array $elements, ?string $name): array
    {
        static $config = [];

        foreach ($elements as $key => $value) {
            if ($key == '#type' && $this->isValidType($value)) {
                if ($name) {
                    $config[\sprintf('webform_modal_composite_field_%s', $name)] = ['key' => $name] + $elements;
                }
            } elseif (\is_array($value)) {
                $config = $this->filterFields($value, $key);
            }
        }

        return $config;
    }

    /**
     * Comprueba si el tipo de un campo es un tipo permitido para mostrar en el composite
     *
     * @param string $type
     * @return boolean
     */
    private function isValidType(string $type): bool
    {
        return \in_array($type, [
            'textfield',
            'email',
            'checkbox',
            'color',
            'date',
            'datetime',
            'number',
            'radio',
            'select',
            'tel',
            'url',
        ]);
    }

    /**
     * Añade los campos seleccionados a la configuración
     *
     * @param FormStateInterface $form_state
     * @return void
     */
    private function addFieldSetting(FormStateInterface $form_state): void
    {
        $fields = $this->getNameFields($form_state->get('element'));
        $element_properties = $form_state->get('element_properties');
        $element_properties['webform_fields'] = [];
        $values = $form_state->getValues();
        $values['webform_fields'] = [];

        foreach ($fields as $field => $setting) {
            if (!isset($values[$field]) || !$values[$field]) {
                continue;
            }

            $values['webform_fields'][$field] = $setting;
            $element_properties['webform_fields'][$field] = $setting;
        }

        $form_state->set('element_properties', $element_properties);
        $form_state->setValues($values);
    }

    /**
     * Borra los campos añadidos al formulario que son para seleccionar que campos queremos mostrar en la tabla
     *
     * @param FormStateInterface $form_state
     * @return void
     */
    private function cleanFields(FormStateInterface $form_state): void
    {
        $element_properties = $form_state->getValues();
        $fields = $this->getNameFields($form_state->get('element'));

        foreach ($fields as $field => $setting) {
            if (array_key_exists($field, $element_properties)) {
                unset($element_properties[$field]);
            }
        }

        $form_state->setValues($element_properties);
    }
}
