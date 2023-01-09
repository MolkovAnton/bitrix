class BasketImport { 
    constructor(settings) {
        this.settings = settings;
        this.popup = this.createPopup();
        if (this.settings.CURRENT_BASKET) {
            this.popup.setContent(this.getMergePopupContent());
            this.popup.show();
        } else {
            this.popup.setContent('<div class="help-popup-start"><div class="ui-tour-popup-text">Обработка корзины</div></div>');
            this.popup.show();
            this.basketImport();
        }
    }
    
    createPopup()
    {
        const popup = BX.PopupWindowManager.create("basket-import-popup", null, {
            autoHide : false,
            offsetTop : 1,
            offsetLeft : 0,
            lightShadow : true,
            closeIcon : false,
            closeByEsc : false,
            overlay: {
               backgroundColor: '#5F7C93', opacity: '50'
            }
        });
        
        return popup;
    }

    createXLSX(data)
    {
        let rows = [];

        for (let key in data) {
            rows.push([key, Number(data[key])]);
        }

        let worksheet = XLSX.utils.aoa_to_sheet(rows),
            workbook = XLSX.utils.book_new();

        XLSX.utils.book_append_sheet(workbook, worksheet, '');
        XLSX.writeFile(workbook, 'unadded_codes.xlsx');
    }
    
    getMergePopupContent()
    {
        const content = BX.create('DIV', {
            attrs: {className: 'help-popup-start'},
            children: [
                BX.create('DIV', {
                    attrs: {className: 'ui-tour-popup-text'},
                    text: this.settings.message.haveOtherProducts
                }),
                BX.create('DIV', {
                    attrs: {className: "ui-btns-wrapper"},
                    children: [
                        BX.create('DIV', {
                            text: 'Объединить',
                            attrs: {className: "ui-btn-main"},
                            events: {click: e => {
                                this.basketImport(true);
                            }}
                        }),
                        BX.create('DIV', {
                            text: 'Перезаписать',
                            attrs: {className: "ui-btn-main"},
                            events: {click: e => {
                                this.basketImport();
                            }}
                        }),
                        BX.create('DIV', {
                            text: 'Отмена',
                            attrs: {className: "ui-btn-close"},
                            events: {click: e => {
                                if (this.settings['SOURCE'] == 'basketImportExcel') {
                                    this.popup.close();
                                } else {
                                    window.location.replace(this.settings.REDIRECT);
                                }
                            }}
                        })
                    ]
                })
            ]
        });
        
        return content;
    }
    
    basketImport(merge = null) {
        BX.ajax.runComponentAction(this.settings.componentName, 'updateBasket', {
            mode: 'class',
            data: {
                param: {
                    MERGE: merge,
                    GUIDS: this.settings.GUIDS,
                    ELASTIC_LOG_INDEX: this.settings.ELASTIC_LOG_INDEX,
                    SOURCE: this.settings.SOURCE
                }
            }
        })
        .then(response => {
            let text = 'Произошла ошибка переноса корзины',
                downloadList = null;

            if (!response.data.ERROR && response.errors.length === 0) {
                const count = this.settings.COUNT - response.data.RESULT;
                const countOfUnadded = Object.keys(response.data['UNADDED']).length;
                const countOfUnrecognized = Object.keys(response.data['UNRECOGNIZED']).length;

                text = 'Перенос корзины завершен.';

                if (count > 0) {
                    text += '<br>';

                    if (countOfUnrecognized) {
                        text += `Не получилось распознать товаров - ${countOfUnrecognized} <br>`;
                    }

                    if (countOfUnadded) {
                        text += `Не получилось загрузить товаров - ${countOfUnadded} `;
                    }

                    downloadList = BX.create('SPAN', {
                        attrs: {className: 'pointer_dark'},
                        text: 'Скачать список',
                        events: {
                            click: () => {
                                this.createXLSX(response.data['UNADDED']);
                            },
                        },
                    });
                }
            }

            const content = BX.create('DIV', {
                attrs: {className: 'help-popup-start'},
                children: [
                    BX.create('DIV', {
                        attrs: {className: 'ui-tour-popup-text'},
                        children: [text, downloadList],
                    }),
                    BX.create('DIV', {
                        attrs: {className: "ui-btns-wrapper"},
                        children: [
                            BX.create('DIV', {
                                text: 'Закрыть',
                                attrs: {className: "ui-btn-main"},
                                events: {click: e => {
                                    window.location.replace(this.settings.REDIRECT);
                                }}
                            }),
                        ]
                    })
                ]
            });
            this.popup.setContent(content);

            let event = new Event('resize');
            window.dispatchEvent(event);
        });
    }
}
