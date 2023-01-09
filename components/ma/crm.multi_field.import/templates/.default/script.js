if(typeof(BX.CrmMultiFieldImportEvents) === "undefined")
{
    BX.CrmMultiFieldImportEvents = function(){
        /* 
         * Инициализация данных. Можно по умолчанию ставить пустые
         * а затем назначать реальные данные в методе initialize
         */
        //this.someData = null;
        this.componentName = ''; //Имя компонента
        this.fileButton = null; //Кнопка выбора файла
        this.preloadContainer = null; //Предпросмотр
        this.resultContainer = null; //Для вывода результата
        this.startButton =  null; //Кнопка начала импорта
        this.inputFile = null; //Файл импорта
        this.importFileFields = []; //Поля для импорта
        this.multipleFields = {}; //Для учета полей с множественным выбором
    };
    
    BX.CrmMultiFieldImportEvents.prototype = 
    {
        //Первая инициализация
        initialize: function(settings)
        {
            /*
             * Метод инициализации, получет данные из template через свойство settings
             */
            this.componentName = settings.componentName;
            this.fileButton = BX(settings.fileButton);
            this.preloadContainer = BX(settings.preloadContainer);
            this.resultContainer = BX(settings.resultContainer);
            this.startButton = BX(settings.startButton);
            this.multipleFields = settings.fieldsNames;
           
            //Обработчики событий
            this.fileButton.addEventListener('change', BX.delegate(this.onFileChange, this));
            this.startButton.addEventListener('click', BX.delegate(this.updateEntitys, this));
            
            //Подключение событий из методов ядра
            //BX.addCustomEvent('BX.Main.User.SelectorController:select', BX.proxy(this.setSomeMethod, this));
        },
        
        onFileChange: function(event)
        {
            this.inputFile = event.target.files[0];
            this.resultContainer.innerHTML = "";
            
            Papa.parse(BX.CrmMultiFieldImportEvents.inputFile, {
                header: true,
                encoding: "Windows-1251",
                skipEmptyLines: true,
                dynamicTyping: true,
                delimiter: ";",
                preview: 3,
                complete: function(results) {
                    BX.CrmMultiFieldImportEvents.showPreview(results.data);
                }
            });
        },
        
        showPreview: function(fields)
        {
            var headers = [];
            var rows = [];
            for (var header in fields[0]) {
                headers.push(BX.create('TH', {
                    text: header
                }));
            }
            rows.push(BX.create('TR', {
                children: headers
            }));
            fields.forEach(function(row) {
                var addRow = [];
                
                for (var header in row) {
                    addRow.push(BX.create('TD', {
                        text: row[header]
                    }));
                }
                rows.push(BX.create('TR', {
                    children: addRow
                }));
            });
            var table = BX.create('TABLE', {
                attrs: {cellpading: 0, cellspasing: 0, className: 'previewTable'},
                children: rows
            });
            table.cellPadding = 0;
            table.cellSpacing = 0;
            this.preloadContainer.innerHTML = table.outerHTML;
            this.startButton.classList.remove('hidden');
        },
        
        updateEntitys: function()
        {
            Papa.parse(BX.CrmMultiFieldImportEvents.inputFile, {
                header: true,
                encoding: "Windows-1251",
                skipEmptyLines: true,
                //dynamicTyping: true,
                delimiter: ";",
                transform: function(value, header) {
                    var newValue = '';
                    if (BX.CrmMultiFieldImportEvents.multipleFields.includes(header)) {
                        newValue = value.split(', ');
                    } else {
                        newValue = value;
                    }
                    return newValue;
                },
                complete: function(results) {
                    BX.CrmMultiFieldImportEvents.importFileFields = results.data;
                    BX.CrmMultiFieldImportEvents.updateAction();
                }
            }); 
        },
        
        updateAction: function()
        {
            var totalCount = 0;
            var goodCount = 0;
            var badCount = 0;
            var allCount = this.importFileFields.length;
            
            var totalCountSpan = BX.create('SPAN', {text: totalCount});
            var goodCountSpan = BX.create('SPAN', {text: goodCount});
            var badCountSpan = BX.create('SPAN', {text: badCount});
            
            var countDiv = BX.create('DIV', {
                attrs: {className: 'total_count'},
                html: "Обработано сущностей - "+totalCountSpan.outerHTML+" из "+allCount+". Удачно: "+goodCountSpan.outerHTML+" | Неудачно: "+badCountSpan.outerHTML
            });
            
            this.resultContainer.append(countDiv);
            
            while (this.importFileFields.length > 0) {
                var items = this.importFileFields.splice(0, 10);
                BX.ajax.runComponentAction(this.componentName, 'updateEntitys', {
                    mode: 'class',
                    data: {
                        post: {
                            ITEMS: items
                        }
                    },
                })
                .then(function(response) {
                    console.log(response);
                    var respData = response.data;
                    
                    goodCount = goodCount + (typeof respData.GOOD !== 'undefined' ? respData.GOOD : 0);
                    badCount = badCount + (typeof respData.BAD !== 'undefined' ? respData.BAD : 0);
                    totalCount = goodCount + badCount;
                    totalCountSpan.innerHTML = totalCount;
                    goodCountSpan.innerHTML = goodCount;
                    badCountSpan.innerHTML = badCount;
                    countDiv.innerHTML = "Обработано сущностей - "+totalCountSpan.outerHTML+" из "+allCount+". Удачно: "+goodCountSpan.outerHTML+" | Неудачно: "+badCountSpan.outerHTML;
                    
                    for (var email in respData.ITEMS) {
                        var itemClass = respData.ITEMS[email].STATUS === 'Y' ? 'good_item' : 'bad_item';
                        BX.CrmMultiFieldImportEvents.resultContainer.append(BX.create('DIV', {
                            attrs: {className: itemClass},
                            text: email+" [id = "+respData.ITEMS[email].ID+"] - "+(respData.ITEMS[email].STATUS === 'Y' ? "OK" : "Error")
                        }));
                    }
                });
            }
        }
    };
    
    //Инициализирует синглтон
    BX.CrmMultiFieldImportEvents.create = function(settings)
    {
        var self = new BX.CrmMultiFieldImportEvents();
        self.initialize(settings);
        return self;
    };
}
