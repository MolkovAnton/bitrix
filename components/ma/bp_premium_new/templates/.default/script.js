if(typeof(BX.BpPremiumNew) === "undefined")
{
    BX.BpPremiumNew = function(){
        this.canAddPremium = false;
        this.canAddProjecPremium = false;
        this.projects = null;
        this.isApprover = false;
        this.mainContainer = null;
        this.mainApproveContainer = null;
        this.sendButton = null;
        this.sendButtonNoApprove = null;
        this.addRowButton = null;
        this.premiumTable = null;
        this.userSelect = null;
        this.ajaxUrl = null;
        this.newSelect = null;
        this.approver = null;
        this.historyContainer = null;
        this.historyApproveContainer = null;
        this.curHistoryPage = 1;
        this.totalHistoryPages = 1;
        this.curHistoryApprovePage = 1;
        this.totalHistoryApprovePages = 1;
        this.curNewPage = 1;
        this.totalNewPage = 1;
        this.currentTab = null;
        this.docsNumContainer = null;
        this.historySelector = null;
        this.currentHistory = null;
    };
    
    BX.BpPremiumNew.prototype = 
    {
        initialize: function(settings)
        {
            this.canAddPremium = settings.canAddPremium;
            this.canAddProjecPremium = settings.canAddProjecPremium;
            this.isApprover = settings.isApprover;
            
            if (this.canAddPremium === true) {
                this.mainContainer = document.getElementsByClassName(settings.container)[0];
                this.succsessMes = document.getElementsByClassName(settings.successMes)[0];
                this.errorMes = document.getElementsByClassName(settings.errorMes)[0];
                this.sendButton = BX(settings.sendButton);
                this.sendButtonNoApprove = BX(settings.sendButtonNoApprove);
                this.addRowButton = BX(settings.addRowButton);
                this.premiumTable = BX(settings.premiumTable);
                this.userSelect = document.querySelector('select[name='+settings.userSelect+']');
                this.newSelect = this.userSelect.cloneNode(true);
                this.approver = settings.approver;
   
                if (this.sendButton !== null) {
                    this.sendButton.addEventListener('click', BX.delegate(this.onSendButtonClick, this));
                }
                this.sendButtonNoApprove.addEventListener('click', BX.delegate(this.onsendButtonNoApproveClick, this));
                this.addRowButton.addEventListener('click', BX.delegate(this.onAddRowButtonClick, this));
                this.userSelect.addEventListener('change', BX.delegate(this.onUserSelect, this));
                this.userSelect.closest('.table_row').getElementsByClassName('delete_button')[0].addEventListener('click', BX.delegate(this.deleteRow, this.userSelect.closest('.table_row')));
            }
            
            if (this.canAddProjecPremium === true) {
                this.projects = settings.projects;
                var projectsDom = document.querySelectorAll('.project');
                projectsDom.forEach(element => {
                    var button = element.querySelector('.ui-btn-success');
                    button.addEventListener('click', BX.delegate(this.onSendButtonClick, this));
                });
            }
            
            this.mainApproveContainer = BX(settings.approveContainer);
            this.docsNumContainer = BX(settings.docsNumContainer);
            this.getNewItems(this.curNewPage);
            this.getApproveHistory(this.curHistoryApprovePage);
            
            this.currentHistory = BX(settings.historyContainer);
            this.historyApproveContainer = BX(settings.historyApproveContainer);
            this.historyContainer = BX(settings.historyContainer);
            this.historySelector = BX(settings.historySelector);
            this.ajaxUrl = settings.ajaxUrl;
            this.currentTab = BX(document.querySelector('.'+settings.headerClass+' button').dataset.bindedElement);
            this.currentTab.classList.remove('hidden');
            document.querySelectorAll('.'+settings.headerClass+' button').forEach(element => element.addEventListener('click', BX.delegate(this.onHeaderButtonClick, this)));
            
            this.historySelector.addEventListener('change', BX.delegate(this.onSelectorChange, this));
            
            this.getHistory(this.curHistoryPage);
        },
        
        onSendButtonClick: function(e)
        { 
            e.preventDefault();
            var form = e.target.form;
            this.sendForm(form);
        },
        
        sendForm: function(form)
        {
            if (!this.validateForm(form)) {
                return;
            }

            var data = this.serializeForm(form);
            var self = this;
            BX.ajax(
                {
                    url: window.location.toString(),
                    method: "POST",
                    dataType: "json",
                    data: {
                        data: data,
                        ACTION: 'ADD_BP',
                        TYPE: typeof form.elements.namedItem('file').files !== 'undefined' && form.elements.namedItem('file').files.length > 0 ? 'INIT' : 'START'
                    },
                    onsuccess: function(resp) {
                        form.parentNode.classList.add("hidden");
                        if (resp === null) {
                            document.querySelector('div[data-form='+form.name+']').classList.remove("hidden");
                        } else {
                            document.querySelector('div[data-form='+form.name+']').classList.remove("hidden");
                        }
                        console.log(resp);
                        
                        if (typeof form.elements.namedItem('file').files !== 'undefined' && form.elements.namedItem('file').files.length > 0) {
                            var newData = new FormData(form);
                            newData.append('ACTION', 'SET_FILE');
                            newData.append('STATUS', 'START');
                            newData.append('ID', resp);
                            $.ajax({
                                url         : window.location.toString(),
                                type        : 'POST',
                                data        : newData,
                                cache       : false,
                                dataType    : 'text',
                                processData : false,
                                contentType : false, 
                                async       : false,
                                success     : function( respond, status, jqXHR ){
                                    console.log(respond);
                                },
                                error: function( jqXHR, status, errorThrown ){
                                    console.log( 'ОШИБКА AJAX запроса: ' + status, jqXHR );
                                }

                            });
                        }
                        
                        self.getHistory(self.curHistoryPage);
                    }
                }
            );
        },
        
        onSelectorChange: function(e)
        {
            var newVal = e.target.value;
            this.currentHistory.classList.add('hidden');
            this.currentHistory = BX(newVal);
            this.currentHistory.classList.remove('hidden');
        },
        
        onsendButtonNoApproveClick: function(e)
        {
            e.preventDefault();
            var curForm = e.target.form;
            if (!this.validateForm(curForm)) {
                return;
            }
            
            var data = this.serializeForm(curForm);
            data.no_agreement_required = "Y";
            var self = this;
            BX.ajax(
                {
                    url: window.location.toString(),
                    method: "POST",
                    dataType: "json",
                    data: {
                        data: data,
                        ACTION: 'ADD_BP',
                        TYPE: typeof curForm.elements.namedItem('file').files !== 'undefined' && curForm.elements.namedItem('file').files.length > 0 ? 'INIT' : 'START'
                    },
                    onsuccess: function(resp) {
                        self.mainContainer.classList.add("hidden");
                        self.succsessMes.classList.remove("hidden");
                        console.log(resp);
                        
                        if (typeof curForm.elements.namedItem('file').files !== 'undefined' && curForm.elements.namedItem('file').files.length > 0) {
                            var newData = new FormData(curForm);
                            newData.append('ACTION', 'SET_FILE');
                            newData.append('STATUS', 'NO_APPROVE');
                            newData.append('ID', resp);
                            $.ajax({
                                url         : window.location.toString(),
                                type        : 'POST',
                                data        : newData,
                                cache       : false,
                                dataType    : 'text',
                                processData : false,
                                contentType : false, 
                                async       : false,
                                success     : function( respond, status, jqXHR ){
                                    console.log(respond);
                                },
                                error: function( jqXHR, status, errorThrown ){
                                    console.log( 'ОШИБКА AJAX запроса: ' + status, jqXHR );
                                }

                            });
                        }
                        
                        self.getHistory(self.curHistoryPage);
                    }
                }
            );
        },
        
        onHeaderButtonClick: function(e)
        {
            e.preventDefault();
            var newTab = BX(e.target.dataset.bindedElement);
            if (newTab === this.currentTab) return;
            this.currentTab.classList.add('hidden');
            newTab.classList.remove('hidden');
            this.currentTab = newTab;
        },
        
        getNewItems: function(page)
        {
            var self = this;
            this.curNewPage = page;
            BX.ajax(
                {
                    url: window.location.toString(),
                    method: "POST",
                    dataType: "json",
                    data: {
                        page: page,
                        ACTION: 'GET_NEW',
                    },
                    onsuccess: function(resp) {
                        self.printNew(resp);
                    }
                }
            );
        },
        
        printNew: function(resp)
        {
            this.mainApproveContainer.innerHTML = null;

            if (typeof resp !== 'object' || resp === null) {
                this.updateDocsCount(0);
                this.mainApproveContainer.append(BX.create('DIV', {
                    attrs: {className: 'bp_error text_center'},
                    text: "Нет заявок для согласования"
                }));
                return;
            }
            this.totalNewPage = resp['PAGES']['NEW'];
            this.updateDocsCount(resp['PAGES']['TOTAL_NEW']);
            
            var self = this;
            
            for (var cont in resp['CONTAINERS']) {
                var container = resp['CONTAINERS'][cont];
                var newRows = [];
                newRows.push(
                    BX.create('DIV', {
                        attrs: {className: 'new_table_header'},
                        children: [
                            BX.create('SPAN', {
                                text: 'Сотрудник'
                            }),
                            BX.create('SPAN', {
                                text: 'Сумма'
                            }),
                            BX.create('SPAN', {
                                text: 'Комментарий'
                            }),
                        ]
                    })
                );

                for (var elem in container.ELEMENTS) {
                    var element = container.ELEMENTS[elem];
                    newRows.push(
                        BX.create('DIV', {
                            attrs: {className: 'new_table_row'},
                            children: [
                                BX.create('SPAN', {
                                    children: [
                                        BX.create('A', {
                                            attrs: {href: '/company/personal/user/'+element.USER_ID+'/'},
                                            text: element.NAME
                                        })
                                    ]
                                }),
                                BX.create('SPAN', {
                                    text: element.SUMM
                                }),
                                BX.create('SPAN', {
                                    text: element.COMMENT
                                }),
                            ]
                        })
                    );
                }
                
                if (typeof container.PROJECT !== 'undefined') {
                    newRows.push(
                          BX.create('DIV', {
                            attrs: {className: 'approver_comment'},
                            children: [
                                BX.create('SPAN', {
                                    attrs: {className: 'comment_title'},
                                    text: 'Подано по проекту: ',
                                }),
                                BX.create('A', {
                                    attrs: {className: 'history_element_file_link', href: container.PROJECT.URL, target: '_blank'},
                                    text: container.PROJECT.NAME
                                })
                            ]
                        })  
                    );
                }
                
                if (typeof container.FILE !== 'undefined') {
                    newRows.push(
                          BX.create('DIV', {
                            attrs: {className: 'history_element_file'},
                            children: [
                                BX.create('SPAN', {
                                    text: 'Файл: ',
                                }),
                                BX.create('A', {
                                    attrs: {className: 'history_element_file_link', href: '/bitrix/tools/disk/uf.php?attachedId='+container.FILE+'&action=download&ncc=1'},
                                    text: 'Скачать'
                                })
                            ]
                        })  
                    );
                }
                
                //Высплывающие окна принять и утвердить
                var acceptBlock = BX.create('DIV', {
                    attrs: {className: 'element_send'},
                    children: [
                        BX.create('DIV', {
                            attrs: {className: 'new_element_status'},
                            children: [
                                BX.create('A', {
                                    attrs: {className: 'creator_name', href: '/company/personal/user/'+container.CREATOR.ID+'/'},
                                    text: container.CREATOR.NAME+" "+container.CREATOR.LAST_NAME
                                }),
                                BX.create('SPAN', {
                                    attrs: {className: 'status_date'},
                                    text: container.DATE_CREATE
                                })
                            ]
                        }),
                        BX.create('TEXTAREA', {
                            attrs: {id: 'popup_comment_approved_'+cont, className: 'pop_up_comment', placeholder: 'Комментарий'},
                        })
                    ]
                });
                BX.PopupWindowManager.create("popup-approved-"+cont, null, {
                    content: acceptBlock,
                    autoHide : true,
                    offsetTop : 1,
                    offsetLeft : 0,
                    lightShadow : true,
                    closeIcon : true,
                    closeByEsc : true,
                    overlay: {
                       backgroundColor: 'black', opacity: '80'
                    },
                    titleBar: {content: BX.create('DIV', {
                        attrs: {className: 'pop_up_title'},
                        text: 'Утвердить премии?'
                    })},
                    buttons: [
                        new BX.PopupWindowButton({
                            text: "Утвердить",
                            className: "popup-window-button-accept",
                            id: 'button_'+cont,
                            events: {click: function(e){
                                self.onSendApproveButtonClick('approved', e.srcElement.id.substring(7), typeof container.SUMM !== 'undefined' ? container.SUMM : null, typeof container.PROJECT !== 'undefined' ? container.PROJECT.ID : null);
                            }}
                        }),
                        new BX.PopupWindowButton({
                            text: "Отмена",
                            className: "webform-button-link-cancel",
                            events: {click: function(){
                                this.popupWindow.close();
                            }}
                        })
                    ]
                });

                var cancelBlock = BX.create('DIV', {
                    attrs: {className: 'element_send'},
                    children: [
                        BX.create('DIV', {
                            attrs: {className: 'new_element_status'},
                            children: [
                                BX.create('A', {
                                    attrs: {className: 'creator_name', href: '/company/personal/user/'+container.CREATOR.ID+'/'},
                                    text: container.CREATOR.NAME+" "+container.CREATOR.LAST_NAME
                                }),
                                BX.create('SPAN', {
                                    attrs: {className: 'status_date'},
                                    text: container.DATE_CREATE
                                })
                            ]
                        }),
                        BX.create('TEXTAREA', {
                            attrs: {id: 'popup_comment_canceled_'+cont, className: 'pop_up_comment', placeholder: 'Комментарий'},
                        })
                    ]
                });
                BX.PopupWindowManager.create("popup-canceled-"+cont, null, {
                    content: cancelBlock,
                    autoHide : true,
                    offsetTop : 1,
                    offsetLeft : 0,
                    lightShadow : true,
                    closeIcon : true,
                    closeByEsc : true,
                    overlay: {
                       backgroundColor: 'black', opacity: '80'
                    },
                    titleBar: {content: BX.create('DIV', {
                        attrs: {className: 'pop_up_title'},
                        text: 'Отклонить премии?'
                    })},
                    buttons: [
                        new BX.PopupWindowButton({
                            text: "Отклонить",
                            className: "webform-button-link-cancel",
                            id: 'button_'+cont,
                            events: {click: function(e){
                                self.onSendApproveButtonClick('canceled', e.srcElement.id.substring(7));
                            }}
                        }),
                        new BX.PopupWindowButton({
                            text: "Отмена",
                            className: "webform-button-link-cancel",
                            events: {click: function(){
                                this.popupWindow.close();
                            }}
                        })
                    ]
                });
                
                var newElement = BX.create('DIV', {
                    attrs: {className: 'new_element'},
                    children: [
                        BX.create('DIV', {
                            attrs: {className: 'new_element_status'},
                            children: [
                                BX.create('A', {
                                    attrs: {className: 'creator_avatar', href: '/company/personal/user/'+container.CREATOR.ID+'/', style: 'background:url("'+container.CREATOR.IMG+'") center no-repeat;background-size: 60px;'},
                                }),
                                BX.create('SPAN', {
                                    text: 'Статус: '+container.STATUS,
                                }),
                                BX.create('SPAN', {
                                    attrs: {className: 'status_date'},
                                    text: container.DATE_CREATE
                                })
                            ]
                        }),
                        BX.create('DIV', {
                            attrs: {className: 'new_element_body'},
                            children: [
                                BX.create('DIV', {
                                    attrs: {className: 'new_table'},
                                    children: newRows
                                }),
                                BX.create('DIV', {
                                    attrs: {className: 'buttons'},
                                    children: [
                                        BX.create('SPAN', {
                                            attrs: {className: 'popup-window-button ui-btn ui-btn-success', onClick: 'BX.BpPremiumNew.popUpShow("approved", '+cont+')'},
                                            text: 'Согласовать'
                                        }),
                                        BX.create('SPAN', {
                                            attrs: {className: 'popup-window-button ui-btn', onClick: 'BX.BpPremiumNew.popUpShow("canceled", '+cont+')'},
                                            text: 'Отклонить'
                                        })
                                    ]
                                })
                            ]
                        })
                    ]
                });
                this.mainApproveContainer.prepend(newElement);
            }
            
            var navPages = [];
            if (this.curNewPage !== 1) {
                navPages.push(
                    BX.create('SPAN', {
                        attrs: {className: 'historyPrev', onClick: 'BX.BpPremiumNew.getNewItems('+(this.curNewPage - 1)+');'},
                        text: '<'
                    })
                );
            }
            navPages.push(
                BX.create('SPAN', {
                    attrs: {className: 'curPage'},
                    text: this.curNewPage + ' из ' + this.totalNewPage
                })
            );
            if (this.curNewPage < this.totalNewPage) {
                navPages.push(
                    BX.create('SPAN', {
                        attrs: {className: 'historyNext', onClick: 'BX.BpPremiumNew.getNewItems('+(this.curNewPage + 1)+');'},
                        text: '>'
                    })
                );
            }
            
            if (resp.constructor === Object && Object.entries(resp).length !== 0) {
                this.mainApproveContainer.append(
                    BX.create('DIV', {
                        attrs: {className: 'navigation'},
                        children: navPages
                    })
                );
            }
            
        },
        
        updateDocsCount: function(num)
        {
            if (num > 0) {
                this.docsNumContainer.textContent = num;
                this.docsNumContainer.classList.remove('hidden');
            } else {
                this.docsNumContainer.textContent = '';
                this.docsNumContainer.classList.add('hidden');
            }
        },
        
        validateForm: function(form)
        {
            if (form.elements.length < 4) {
                alert('Не выбран ни один пользователь');
                return false;
            }
            
            var users = [];
            var valid = true;
            var summ = 0;
            
            for (var i = 0; i < form.elements.length; i++) {
                var field = form.elements[i];

                if (field.name === 'summ') {
                    if (field.value === '') {
                        valid = false;
                        field.classList.add('error');
                        field.placeholder = "Обязательное";
                    } else {
                        summ += parseInt(field.value);
                    }
                    
                } else if (field.name === 'sub_name') {
                    if (users.includes(field.value)) {
                        valid = false;
                        field.classList.add('error');
                        alert('Один пользователь выбран несколько раз');
                    } else {
                        users.push(field.value);
                    }
                }
            }
            
            if (typeof form.elements.project !== 'undefined' && this.projects[form.elements.project.value].BUDGET_LEFT.SUMM < summ) {
                BX.PopupWindowManager.create("popup-project-error-"+form.elements.project.value, null, {
                    content: BX.create('DIV', {
                        attrs: {className: 'bp_error'},
                        children: [
                            BX.create('P', {
                                text: 'Ошибка! Операция не выполнена.'
                            }),
                            BX.create('P', {
                                text: 'Сумма поданных премий больше доступного остатка по проекту: в настройках проекта указан бюджет на премии в размере ' + this.projects[form.elements.project.value].BUDGET.SUMM + ' ' + this.projects[form.elements.project.value].BUDGET.CURRENCY
                                        + ' рублей, ранее вы уже подали премии на сумму ' + this.projects[form.elements.project.value].APPROVED_SUMM.SUMM + ' ' + this.projects[form.elements.project.value].APPROVED_SUMM.CURRENCY
                                        + ', доступный остаток составляет ' + this.projects[form.elements.project.value].BUDGET_LEFT.SUMM + ' ' + this.projects[form.elements.project.value].BUDGET_LEFT.CURRENCY
                            }),
                            BX.create('SPAN', {
                                text: 'Вы можете:'
                            }),
                            BX.create('BR', {}),
                            BX.create('SPAN', {
                                text: 'а) изменить размер премий, чтобы сумма стала в рамках доступного остатка,'
                            }),
                            BX.create('BR', {}),
                            BX.create('SPAN', {
                                text: 'б) пересогласовать бюджет на премии по проекту и внести изменения в настройки проекта.'
                            }),
                        ]
                    }),
                    autoHide : true,
                    offsetTop : 1,
                    offsetLeft : 0,
                    lightShadow : true,
                    closeIcon : true,
                    closeByEsc : true,
                    overlay: {
                       backgroundColor: 'black', opacity: '80'
                    },
                    buttons: [
                        new BX.PopupWindowButton({
                            text: "Отмена",
                            className: "webform-button-link-cancel",
                            events: {click: function(){
                                BX.PopupWindowManager.getCurrentPopup().destroy();
                            }}
                        })
                    ]
                }).show();
                
                return false;
            }
            
            return valid;
        },
        
        onAddRowButtonClick: function()
        {
            var newSelect = this.newSelect.cloneNode(true);
            var defaultFor = newSelect.dataset.for;

            var newRow = BX.create("DIV", {
                    attrs: { className: "table_row" },
                    children: [
                        BX.create("DIV", {
                            attrs: { className: "table_cell select" },
                            children: [
                                newSelect,
                            ]
                        }),
                        BX.create("DIV", {
                            attrs: { className: "table_cell summ" },
                            children: [
                                BX.create("INPUT", { 
                                   attrs: { className: "table_cell", type: "text", name: "summ", onfocus: "this.classList.remove('error');"},
                                   dataset: {for: defaultFor}
                                }) ,
                            ]
                        }),
                        BX.create("DIV", {
                            attrs: { className: "table_cell" },
                            children: [
                                BX.create("TEXTAREA", { 
                                   attrs: { className: "table_cell", type: "text", name: "comment"},
                                   dataset: {for: defaultFor}
                                }) ,
                            ]
                        }),
                        BX.create("DIV", {
                            attrs: { className: "table_cell" },
                            children: [
                                BX.create("SPAN", { 
                                   attrs: { className: "delete_button", type: "text", name: "delete"},
                                   dataset: {for: defaultFor}
                                }) ,
                            ]
                        }),
                    ]
                }
            );
            this.premiumTable.appendChild(newRow);;
            newRow.getElementsByClassName('delete_button')[0].addEventListener('click', BX.delegate(this.deleteRow, newRow));
            newSelect.addEventListener('change', BX.delegate(this.onUserSelect, this));
        },
        
        deleteRow: function(e)
        {
            e.target.removeEventListener('click', BX.BpPremiumNew.deleteRow);
            this.remove();
        },
        
        onUserSelect: function(e)
        {
            var select = e.target;
            var newId = e.target.value;
            var container = select.closest('.table_row');
            var arrToUpdate = container.querySelectorAll('[data-for]');
            
            for (var i=0; i<arrToUpdate.length; i++) {
                arrToUpdate[i].dataset.for = newId;
            }
        },
        
        serializeForm: function (form) {

            // Setup our serialized data
            var serialized = {users: {}};

            // Loop through each field in the form
            for (var i = 0; i < form.elements.length; i++) {

                    var field = form.elements[i];

                    // Don't serialize fields without a name, submits, buttons, file and reset inputs, and disabled fields
                    if (!field.name || field.disabled || field.type === 'reset' || field.type === 'file' || field.type === 'submit' || field.type === 'button') continue;

                    if (field.dataset.for) {
                        var dataFor = field.dataset.for;
                        if (typeof serialized.users[dataFor] === "undefined") {
                            serialized.users[dataFor] = {};
                        } 
                        serialized.users[dataFor][field.name] = field.value; 
                    } else if(field.type === 'checkbox') {
                        serialized[field.name] = (field.checked === true) ? 1 : 0;
                    } else {
                        serialized[field.name] = field.value;
                    }
            }
            return serialized;
        },
        
        getHistory: function(page)
        {
            var self = this;
            this.curHistoryPage = page;
            BX.ajax(
                {
                    url: window.location.toString(),
                    method: "POST",
                    dataType: "json",
                    data: {
                        page: page,
                        ACTION: 'GET_HISTORY',
                    },
                    onsuccess: function(resp) {
                        self.printHistory(resp);
                    }
                }
            );
        },
        
        printHistory: function(resp)
        {
            this.historyContainer.innerHTML = '';
            if (typeof resp !== 'object' || resp === null) {
                this.historyContainer.append(BX.create('DIV', {
                    attrs: {className: 'bp_error text_center'},
                    text: "Вы ещё не подавали заявки на премии"
                }));
                return;
            }
            this.totalHistoryPages = resp['PAGES'];
            
            for (var cont in resp['CONTAINERS']) {
                var container = resp['CONTAINERS'][cont];
                var historyRows = [];
                historyRows.push(
                    BX.create('DIV', {
                        attrs: {className: 'new_table_header'},
                        children: [
                            BX.create('SPAN', {
                                text: 'Сотрудник'
                            }),
                            BX.create('SPAN', {
                                text: 'Сумма'
                            }),
                            BX.create('SPAN', {
                                text: 'Комментарий'
                            }),
                        ]
                    })
                );
        
                for (var elem in container.ELEMENTS) {
                    var element = container.ELEMENTS[elem];
                    historyRows.push(
                        BX.create('DIV', {
                            attrs: {className: 'new_table_row'},
                            children: [
                                BX.create('SPAN', {
                                    children: [
                                        BX.create('A', {
                                            attrs: {href: '/company/personal/user/'+element.USER_ID+'/'},
                                            text: element.NAME
                                        })
                                    ]
                                }),
                                BX.create('SPAN', {
                                    text: element.SUMM
                                }),
                                BX.create('SPAN', {
                                    text: element.COMMENT
                                }),
                            ]
                        })
                    );
                }
                
                if (typeof container.PROJECT !== 'undefined') {
                    historyRows.push(
                          BX.create('DIV', {
                            attrs: {className: 'approver_comment'},
                            children: [
                                BX.create('SPAN', {
                                    attrs: {className: 'comment_title'},
                                    text: 'Подано по проекту: ',
                                }),
                                BX.create('A', {
                                    attrs: {className: 'history_element_file_link', href: container.PROJECT.URL, target: '_blank'},
                                    text: container.PROJECT.NAME
                                })
                            ]
                        })  
                    );
                }

                if (typeof container.FILE !== 'undefined') {
                    historyRows.push(
                          BX.create('DIV', {
                            attrs: {className: 'history_element_file'},
                            children: [
                                BX.create('SPAN', {
                                    text: 'Файл: ',
                                }),
                                BX.create('A', {
                                    attrs: {className: 'history_element_file_link', href: '/bitrix/tools/disk/uf.php?attachedId='+container.FILE+'&action=download&ncc=1'},
                                    text: 'Скачать'
                                })
                            ]
                        })  
                    );
                }
                
                var histElem = BX.create('DIV', {
                    attrs: {className: 'new_element'},
                    children: [
                        BX.create('DIV', {
                            attrs: {className: 'new_element_status '+container.STATUS_ID},
                            children: [
                                BX.create('A', {
                                    attrs: {className: 'creator_avatar', href: '/company/personal/user/'+container.APPROVER.ID+'/', style: 'background:url("'+container.APPROVER.IMG+'") center no-repeat;background-size: 60px;'},
                                }),
                                BX.create('SPAN', {
                                    text: 'Статус: '+container.STATUS,
                                }),
                                BX.create('SPAN', {
                                    attrs: {className: 'status_date'},
                                    text: container.DATE_CREATE
                                })
                            ]
                        }),
                        BX.create('DIV', {
                            attrs: {className: 'new_element_body'},
                            children: [
                                BX.create('DIV', {
                                    attrs: {className: 'new_table'},
                                    children: historyRows
                                }),
                                BX.create('DIV', {
                                    attrs: {className: 'approver_comment'},
                                    children: [
                                        BX.create('DIV', {
                                            attrs: {className: 'comment_title'},
                                            text: 'Комментарий:'
                                        }),
                                        BX.create('DIV', {
                                            attrs: {className: 'comment_text'},
                                            text: container.COMMENT
                                        }),
                                    ]
                                }),
                            ]
                        })
                    ]
                });
                this.historyContainer.prepend(histElem);
            }
            
            var navPages = [];
            if (this.curHistoryPage !== 1) {
                navPages.push(
                    BX.create('SPAN', {
                        attrs: {className: 'historyPrev', onClick: 'BX.BpPremiumNew.getHistory('+(this.curHistoryPage - 1)+');'},
                        text: '<'
                    })
                );
            }
            navPages.push(
                BX.create('SPAN', {
                    attrs: {className: 'curPage'},
                    text: this.curHistoryPage + ' из ' + this.totalHistoryPages
                })
            );
            if (this.curHistoryPage < this.totalHistoryPages) {
                navPages.push(
                    BX.create('SPAN', {
                        attrs: {className: 'historyNext', onClick: 'BX.BpPremiumNew.getHistory('+(this.curHistoryPage + 1)+');'},
                        text: '>'
                    })
                );
            }
            
            if (resp.constructor === Object && Object.entries(resp).length !== 0) {
                this.historyContainer.append(
                    BX.create('DIV', {
                        attrs: {className: 'navigation'},
                        children: navPages
                    })
                );
            }
        },
        
        getApproveHistory: function(page)
        {
            var self = this;
            this.curHistoryApprovePage = page;
            BX.ajax(
                {
                    url: window.location.toString(),
                    method: "POST",
                    dataType: "json",
                    data: {
                        page: page,
                        ACTION: 'GET_APPROVE_HISTORY',
                    },
                    onsuccess: function(resp) {
                        self.printApproveHistory(resp);
                    }
                }
            );
        },
        
        printApproveHistory: function(resp)
        {
            this.historyApproveContainer.innerHTML = null;
            if (typeof resp !== 'object' || resp === null) {
                this.historyApproveContainer.append(BX.create('DIV', {
                    attrs: {className: 'bp_error text_center'},
                    text: "Вы ещё не согласовывали заявки на премии"
                }));
                return;
            }
            this.totalHistoryApprovePages = resp['PAGES']['HISTORY'];
            
            for (var cont in resp['CONTAINERS']) {
                var container = resp['CONTAINERS'][cont];
                var historyRows = [];
                historyRows.push(
                    BX.create('DIV', {
                        attrs: {className: 'new_table_header'},
                        children: [
                            BX.create('SPAN', {
                                text: 'Сотрудник'
                            }),
                            BX.create('SPAN', {
                                text: 'Сумма'
                            }),
                            BX.create('SPAN', {
                                text: 'Комментарий'
                            }),
                        ]
                    })
                );
        
                for (var elem in container.ELEMENTS) {
                    var element = container.ELEMENTS[elem];
                    historyRows.push(
                        BX.create('DIV', {
                            attrs: {className: 'new_table_row'},
                            children: [
                                BX.create('SPAN', {
                                    children: [
                                        BX.create('A', {
                                            attrs: {href: '/company/personal/user/'+element.USER_ID+'/'},
                                            text: element.NAME
                                        })
                                    ]
                                }),
                                BX.create('SPAN', {
                                    text: element.SUMM
                                }),
                                BX.create('SPAN', {
                                    text: element.COMMENT
                                }),
                            ]
                        })
                    );
                }
                
                if (typeof container.PROJECT !== 'undefined') {
                    historyRows.push(
                          BX.create('DIV', {
                            attrs: {className: 'approver_comment'},
                            children: [
                                BX.create('SPAN', {
                                    attrs: {className: 'comment_title'},
                                    text: 'Подано по проекту: ',
                                }),
                                BX.create('A', {
                                    attrs: {className: 'history_element_file_link', href: container.PROJECT.URL, target: '_blank'},
                                    text: container.PROJECT.NAME
                                })
                            ]
                        })  
                    );
                }

                if (typeof container.FILE !== 'undefined') {
                    historyRows.push(
                          BX.create('DIV', {
                            attrs: {className: 'history_element_file'},
                            children: [
                                BX.create('SPAN', {
                                    text: 'Файл: ',
                                }),
                                BX.create('A', {
                                    attrs: {className: 'history_element_file_link', href: '/bitrix/tools/disk/uf.php?attachedId='+container.FILE+'&action=download&ncc=1'},
                                    text: 'Скачать'
                                })
                            ]
                        })  
                    );
                }
                
                var histElem = BX.create('DIV', {
                    attrs: {className: 'new_element'},
                    children: [
                        BX.create('DIV', {
                            attrs: {className: 'new_element_status ' + container.STATUS_ID},
                            children: [
                                BX.create('A', {
                                    attrs: {className: 'creator_avatar', href: '/company/personal/user/'+container.CREATOR.ID+'/', style: 'background:url("'+container.CREATOR.IMG+'") center no-repeat;background-size: 60px;'},
                                }),
                                BX.create('SPAN', {
                                    text: 'Статус: '+container.STATUS,
                                }),
                                BX.create('SPAN', {
                                    attrs: {className: 'status_date'},
                                    text: container.DATE_CREATE
                                })
                            ]
                        }),
                        BX.create('DIV', {
                            attrs: {className: 'new_element_body'},
                            children: [
                                BX.create('DIV', {
                                    attrs: {className: 'new_table'},
                                    children: historyRows
                                }),
                                BX.create('DIV', {
                                    attrs: {className: 'approver_comment'},
                                    children: [
                                        BX.create('DIV', {
                                            attrs: {className: 'comment_title'},
                                            text: 'Комментарий:'
                                        }),
                                        BX.create('DIV', {
                                            attrs: {className: 'comment_text'},
                                            text: container.COMMENT
                                        }),
                                    ]
                                }),
                            ]
                        })
                    ]
                });
                this.historyApproveContainer.prepend(histElem);
            }
            
            var navPages = [];
            if (this.curHistoryApprovePage !== 1) {
                navPages.push(
                    BX.create('SPAN', {
                        attrs: {className: 'historyPrev', onClick: 'BX.BpPremiumNew.getApproveHistory('+(this.curHistoryApprovePage - 1)+');'},
                        text: '<'
                    })
                );
            }
            navPages.push(
                BX.create('SPAN', {
                    attrs: {className: 'historyCurPage'},
                    text: this.curHistoryApprovePage + ' из ' + this.totalHistoryApprovePages
                })
            );
            if (this.curHistoryApprovePage < this.totalHistoryApprovePages) {
                navPages.push(
                    BX.create('SPAN', {
                        attrs: {className: 'historyNext', onClick: 'BX.BpPremiumNew.getApproveHistory('+(this.curHistoryApprovePage + 1)+');'},
                        text: '>'
                    })
                );
            }
            
            if (resp.constructor === Object && Object.entries(resp).length !== 0) {
                this.historyApproveContainer.append(
                    BX.create('DIV', {
                        attrs: {className: 'navigation'},
                        children: navPages
                    })
                );
            }
        },
        
        onSendApproveButtonClick: function(type, id, summ = null, project = null)
        {
            var self = this;
            
            BX.ajax(
                {
                    url: window.location.toString(),
                    method: "POST",
                    dataType: "json",
                    data: {
                        data: {ID: id, TYPE: type, COMMENT: BX('popup_comment_'+type+'_'+id).value, SUMM: summ, PROJECT: project},
                        ACTION: 'CHANGE_STATUS',
                    },
                    onsuccess: function(resp) {console.log(resp);
                        if (resp !== null) {
                            BX.PopupWindowManager.getCurrentPopup().destroy();
                            self.getApproveHistory(self.curHistoryApprovePage);
                            self.getNewItems(self.curNewPage);
                        }
                        
                    }
                }
            );
        },
        
        popUpShow: function(type, id)
        {
            BX.PopupWindowManager.create('popup-'+type+'-'+id).show();
        }
    };
    
    BX.BpPremiumNew.create = function(settings)
    {
        var self = new BX.BpPremiumNew();
        self.initialize(settings);
        return self;
    };
}
