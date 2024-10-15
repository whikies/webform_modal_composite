
;(function ($) {
    let elementClicked = undefined;

    /*=======================================================================
    Eventos para controlar el modal con bootstrap
    =======================================================================*/

    $(document).on('ajaxSuccess', function (e, d, f) {
        if (!document.activeElement.classList.contains('webform-modal-composite-buttom')) {
            return;
        }

        const element = document.activeElement;
        document.activeElement.blur();

        const event = new CustomEvent('webform_modal_open', {
            detail: {
                dialog: $(element).data('dialog'),
                element: $('#modal-composite-body', $(element).data('modal')),
                settings: $(element).data('settings'),
                type: 'bootstrap'
            }
        });
        window.dispatchEvent(event);
    });

    $(document).on('click', '.webform-modal-composite-buttom', function (e) {
        e.preventDefault();

        if (typeof bootstrap === 'undefined') {
            window.alert('Undefined bootstrap');
            return;
        }

        const settings = $(this).data('settings');
        let modal = $('#webform-modal-composite-dialog');

        if (modal.length === 0) {
            modal = $('<div>', {
                class: 'modal fade',
                tabindex: '-1',
                ariaHidden: 'true',
                dataBsBackdrop: 'static',
                id: 'webform-modal-composite-dialog',
                html: `<div class="modal-dialog" style="max-width: ${settings.width}px">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">${settings.title}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body" id="modal-composite-body"></div>
                            </div>
                        </div>`
            });

            $('body').append(modal);
        } else {
            $('.modal-title', modal).text(settings.title);
            $('.modal-body', modal).children().remove();
        }

        const dialog = new bootstrap.Modal(modal.get(0));
        dialog.show();
        $(this).data('modal', modal);
        $(this).data('dialog', dialog);

        Drupal.ajax({
            url: $(this).attr('href'),
            wrapper: 'modal-composite-body',
            method: 'html',
        }).execute();
    });

    // Cuando se cierra el modal eliminamos el botón registrado
    window.addEventListener('hidden.bs.modal', (e) => {
        elementClicked = undefined;
        $('#webform-modal-composite-dialog')?.remove();
    });

    /*=======================================================================
    Eventos para controlar el modal con bootstrap
    =======================================================================*/

    /*=======================================================================
    Eventos para controlar el modal con un dialog
    =======================================================================*/

    // Cuando se cierra el modal eliminamos el botón registrado
    window.addEventListener('dialog:beforeclose', (e) => {
        elementClicked = undefined;
    });

    // Cuando se crea el modal disapramos el evento para cargar el formulario
    window.addEventListener('dialog:aftercreate', (e) => {
        if ($(e.target).hasClass('webform-modal-composite-dialog')) {
            const event = new CustomEvent('webform_modal_open', {
                detail: {
                    dialog: e.dialog,
                    element: e.target,
                    settings: e.settings,
                    type: 'dialog'
                }
            });
            window.dispatchEvent(event);
        }
    });

    /*=======================================================================
    Eventos para controlar el modal con un dialog
    =======================================================================*/

    // Cierra el modal
    const closeModal = function (type, dialog) {
        switch (type) {
            case 'dialog':
                dialog.close();
                break;

            case 'bootstrap':
                dialog.hide();
                break;
        }
    }

    // Buscamos los contenedores wrapper para normalizarlos y crear el inner
    const normalizeWrapper = function (element) {
        element.querySelectorAll('div.modal-field-composite-field-wrapper').forEach(function (element) {
            $(element).wrapInner("<div class='modal-field-composite-field-wrapper-inner'></div>")
        });
    }

    // Busca un element de form recursiva
    const searchElement = (element, selector, i = 0) => {
        let e = $(selector, element);

        if (e.length === 0 && i < 5) {
            e = searchElement(element.parentElement, selector, ++i);
        }

        return e;
    }

    // Los campos del modal que estén en el formulario principal no podrán ser editados manualmente
    const handleEventChange = function (e) {
        const element = e.target;

        if (element.classList.contains('modal-field-composite-field')) {
            e.preventDefault();
            e.stopPropagation();

            const elementValues = searchElement(element, '.modal-field-composite-field-values');

            if (elementValues.length) {
                const webformValues = JSON.parse($(elementValues)?.val() || '{}');
                const name = $(element).data('values-key');

                if (name) {
                    $(element).val(webformValues?.data?.[name]);
                }
            }
        }
    }

    // Función para buscar el tr superior en formulario multiples
    const getSelector = function (isMultiple) {
        if (!isMultiple) {
            return undefined;
        }

        return elementClicked.closest('tr');
    }

    normalizeWrapper(document);

    // Cuando se añade una fila nueva a la tabla en formularios multiples abrimos el modal para añadir un registro a la nueva fila
    $(document).on('ajaxComplete', function (e, d, f) {
        if (f?.extraData?._triggering_element_name) {
            const container = f.extraData._triggering_element_name.replace(/_add(_\d+)?$/, '');
            const element = $(`#${container} .modal-field-composite-container`);

            if (element.length) {
                const tr = $('table tr:last', element);
                const link = $('.modal-field-composite-webform-modal-link', tr);

                if (link) {
                    link.click();
                    elementClicked = link;
                }

                normalizeWrapper(element.get(0));
            }
        }
    });

    // Cuando hacemos click en el boton para abrir el modal registramos que botón
    // Lo registramos para extraer los valores correcto según la fila que se este editando
    // Esto se hace para solucionar el problema de edición de formularios multiples
    document.addEventListener('click', function (e) {
        const element = e.target;

        if (element.tagName == 'A' && element.classList.contains('modal-field-composite-webform-modal-link')) {
            elementClicked = element;
        }
    }, true);

    // Cuando se hace click en los campos del modal que estén en el formulario principal abrirá el modal
    document.addEventListener('click', function (e) {
        const element = e.target;

        if (element.classList.contains('modal-field-composite-field-wrapper-inner')) {
            e.preventDefault();
            e.stopPropagation();

            const button = searchElement(element, '.modal-field-composite-webform-modal-link');

            if (button) {
                button.click();
                elementClicked = button;
            }
        }
    }, true);

    document.addEventListener('keyup', function (e) {
        handleEventChange(e)
    }, true);

    document.addEventListener('change', function (e) {
        handleEventChange(e)
    }, true);

    // Cuando el modal del formulario se abre lo hace mostrando un loader
    // Posteriormente hacemos una petición para cargar el formulario y centrar el modal
    window.addEventListener('webform_modal_open', (e) => {
        const type = e.detail?.type;
        const process = e.detail?.settings?.processForm;
        const render = e.detail?.settings?.renderForm;
        const fields = e.detail?.settings?.fieldsForm;
        const saveSubmission = e.detail?.settings?.saveSubmission;
        const selectorFieldHidden = e.detail?.settings?.selectorFieldHidden;
        const selectorModalElement = e.detail?.settings?.selectorModalElement;
        const multiple = e.detail?.settings?.multiple === '1';
        const dialogElement = e.detail.element;
        const dialog = e.detail.dialog;
        const webformValues = JSON.parse($(selectorFieldHidden, getSelector(multiple))?.val() || '{}');
        let sid = undefined;

        if (saveSubmission && webformValues?.sid) {
            sid = webformValues.sid;
        }

        if (type == 'dialog') {
            $(dialogElement).dialog('option', 'resizable', true);
        }

        // Petición para cargar el formulario
        $.ajax({
            url: render,
            method: 'post',
            data: {
                data: webformValues?.data || {},
                sid,
            },
            success: function (respose) {
                $(dialogElement).html(respose);
                const form = $('form', dialogElement);

                if (type == 'dialog') {
                    $(dialogElement).closest('.ui-dialog ').position({
                        my: "center",
                        at: "center",
                        of: window
                    });
                }

                $('.webform-submission-navigation', dialogElement).remove();

                if (!form || !process) {
                    return;
                }

                // Cuando carga el formulario generar el listener para el evento submit
                form.on('submit', (e) => {
                    e.preventDefault();
                    const data = form.serializeArray();
                    data.push({ name: 'webformModalFields', value: fields });
                    data.push({ name: 'sid', value: sid });
                    data.push({ name: 'webformModalSaveSubmission', value: saveSubmission });

                    // Cuando se envía el formulario, enviamos los datos para procesarlos y validarlos
                    // Si está todo correcto guardamos los datos seleccionados en el formulario principal
                    // Y guardamos toda la info en el campo webform_modal_values
                    $.ajax({
                        url: process,
                        data,
                        method: 'post',
                        success: function (response) {
                            if (response?.ok) {
                                for (const key in response.fields) {
                                    if (Object.prototype.hasOwnProperty.call(response.fields, key)) {
                                        const value = response.fields[key];
                                        $(`${selectorModalElement}[name$="[${key}]"]`, getSelector(multiple)).val(value);
                                    }
                                }

                                const {ok, ...rest } = response;
                                $(selectorFieldHidden, getSelector(multiple))?.val(JSON.stringify(rest || {}));
                            }

                            closeModal(type, dialog);
                        },
                        error: function (error) {
                            console.log(error)
                        }
                    });
                });
            },
            error: function (error) {
                console.log(error)
            }
        })
    })
})(jQuery)
