if(typeof(BX.InnChecker) === "undefined")
{
    BX.InnChecker = function(){
        this.form = null;
        this.sendButton = null;
        this.import = null;
        this.types = [];
        this.checkboxes = null;
        this.ajaxUrl = null;
        this.propNames = null;
        this.propNamesInFile = null;
        this.outputFile = "";
        this.outputFileDif = "";
        this.typeNames = null;
        this.resultContainer = null;
        this.downloadLink = null;
        this.rowCount = 0;
    };
    
    BX.InnChecker.prototype = 
    {
        initialize: function(settings)
        {
           this.form = BX(settings.form);
           this.sendButton = BX(settings.sendButton);
           this.import = BX(settings.import);
           this.ajaxUrl = settings.ajaxUrl;
           this.propNames = settings.propNames;
           this.propNamesInFile = settings.propNamesInFile;
           this.resultContainer = BX(settings.resultContainer);
           this.downloadLink = BX(settings.downloadLink);
           
           this.typeNames = {
               'COMPANY': 'Компания',
               'LEAD': 'Лид',
               'CONTACT': 'Контакт',
               'DEAL': 'Сделка'
           };
           
           this.checkboxes = this.form.querySelectorAll('input[type="checkbox"]');
           
           this.sendButton.addEventListener('click', BX.delegate(this.onSendButtonClick, this));
           var self = this;
           this.checkboxes.forEach(function(val, index, arr){
                if (arr[index].checked) {
                    self.types.push(arr[index].value); 
                }
                arr[index].addEventListener('change', BX.delegate(self.onCheckboxClick, self)); 
           });
           
        },
        
        onCheckboxClick: function(e)
        {
            if (e.target.checked) {
                this.types.push(e.target.value); 
            } else {
                this.types.splice(this.types.indexOf(e.target.value), 1);
            }
        },

        onSendButtonClick: function(e)
        { 
            e.preventDefault();
            var self = this;
            
            if (this.import.files.length === 0) {
                alert("Файл импорта не выбран");
                return;
            }
            
            this.outputFile = "Значение;Сущность;Имя;Поле поиска;\r\n";
            this.outputFileDif = "";
            for (let type of this.types) {
                this.outputFileDif += type+";";
            }
            this.outputFileDif += "\r\n";
            
            this.resultContainer.innerHTML = '';
            this.resultContainer.appendChild(
                BX.create('TABLE', {
                    children: [
                        BX.create('TR', {
                            children: [
                                BX.create('TH', {text: "Значение"}),
                                BX.create('TH', {text: "Сущность"}),
                                BX.create('TH', {text: "В каком поле найдено"}),
                                BX.create('TH', {text: "Тип сущности"}),
                            ]
                        })
                    ]
                })
            );
            
            Papa.parse(this.import.files[0], {
                header: true,
                encoding: "Windows-1251",
                skipEmptyLines: true,
                dynamicTyping: true,
                delimiter: ";",
                transformHeader: function(header) {
                    if (self.types.includes(header.toLowerCase())) return header;
                },
                chunk: function(results, parser) {
                    var valid = false;
                    for (var i=0; i<results.meta.fields.length; i++) {
                        if (typeof results.meta.fields[i] !== "undefined") {
                            valid = true;
                            break;
                        }
                    }
                    
                    if (!valid) {
                        parser.abort();
                        alert("В файле не найдены необходимые колонки");
                        return;
                    }
                    
                    var inputData = {};
                    self.types.forEach(val => inputData[val] = []);
                    for (let row in results.data) {
                        for (let col in results.data[row]) {
                            if (inputData[col.toLowerCase()] !== undefined && results.data[row][col] !== null) {
                                let resVal = typeof results.data[row][col].trim === 'function' ? results.data[row][col].trim() : results.data[row][col];
                                inputData[col.toLowerCase()].push(resVal);
                            }    
                        }
                    }
                    self.ajaxSearch(inputData, parser);
                }
            });
        },
        
        ajaxSearch: function (inputData, parser)
        {
            parser.pause();
            var self = this;

            BX.ajax(
                {
                    url: self.ajaxUrl,
                    method: "POST",
                    dataType: "json",
                    data: {
                        DATA: inputData,
                        PROP_NAMES: self.propNames,
                        PROP_NAME_IN_FILE: self.propNamesInFile,
                        ACTION: 'SEARCH',
                    },
                    onsuccess: function(resp) {
                        if (resp['ERROR']) {
                            parser.abort();
                            console.log(resp['ERROR']);
                        } else {
                            var respDif = resp.DIFFERENCE;
                            delete resp.DIFFERENCE;
                            
                            self.printResult(resp); //Вывод на страницу найденных элементов
                            self.putDataToFile(resp); //Наполняем файл данными для экспорта
                            self.putDataToFileDif(respDif); //Наполняем файл для экспорта не найденными данными
                            
                            parser.resume(); //Запускаем обработку следующей порции данных
                            
                            if (self.outputFile.length > 0) self.download(self.outputFile, 'output.csv', 'text/csv;charset=Windows-1251', "Скачать найденные", 'find'); //Формируем ссылку для скачивания файла экспорта
                            if (self.outputFileDif.length > 0) self.download(self.outputFileDif, 'outputNotFound.csv', 'text/csv;charset=Windows-1251', "Скачать ненайденные", 'notFind'); //Формируем ссылку для скачивания файла экспорта
                        }
                    }
                }
            );
        },
        
        putDataToFile: function(data)
        {
            var self = this;
            var str = '';
            var title = '';
            var entityType = '';
            var value = '';
            for (var type in data) {
                for (var id in data[type]) {
                    if (typeof data[type][id]['TITLE'] == 'undefined') {
                        title = data[type][id]['NAME'];
                    } else {
                        title = data[type][id]['TITLE'];
                    }
                    entityType = data[type][id]['TYPE'] === 'INN_REQ' ? 'инн (реквизит)' : self.propNamesInFile[data[type][id]['TYPE']];
                    value = data[type][id]['FIND'];
                    str = value+';'+title+';'+entityType+';'+self.typeNames[type]+';\r\n';
                    this.outputFile += str;
                }
            }
        },
        
        putDataToFileDif: function(data)
        {
            var invertPropNames = {};
            Object.getOwnPropertyNames(this.propNamesInFile).forEach(val => invertPropNames[this.propNamesInFile[val]] = val);
            
            var str = '';
            var ln = 0;
            for (let type of this.types) {
                if (data[invertPropNames[type]].length > ln) ln = data[invertPropNames[type]].length;
            }
            for (let i=0; i<ln; i++) {
                for (let type of this.types) {
                    str += typeof data[invertPropNames[type]][i] !== 'undefined' ? data[invertPropNames[type]][i]+";" : ";";
                }
                str += '\r\n';
            }
            this.outputFileDif += str;
        },
        
        printResult: function(data)
        {
            var self = this;
            self.rowCount = 0;
            var title = '';
            for (var type in data) {
                for (var id in data[type]) {
                    //Ограничение на вывод 100 строк
                    if (self.rowCount > 100) {
                        self.resultContainer.appendChild(BX.create('DIV', {attrs: {className: "bottom-info"}, text: "Выведены первые 100 найденных сущностей"}));
                        return;
                    } 
                    //Выбор названия сущности - для контакта NAME, для остальных TITLE
                    if (typeof data[type][id]['TITLE'] == 'undefined') {
                        title = data[type][id]['NAME'];
                    } else {
                        title = data[type][id]['TITLE'];
                    }
                    
                    //Формирование строки для добавления в таблицу результатов
                    var tr = BX.create('TR');
                    tr.appendChild(BX.create('TD', {text: data[type][id]['FIND']}));
                    tr.appendChild(
                        BX.create('TD', {
                            children: [
                                BX.create('A',{
                                    attrs: {href: data[type][id].url},
                                    text: title
                                })
                            ]
                        })
                    );
                    tr.appendChild(BX.create('TD', {text: data[type][id]['TYPE'] === 'INN_REQ' ? 'инн (реквизит)' : self.propNamesInFile[data[type][id]['TYPE']]}));
                    tr.appendChild(BX.create('TD', {text: self.typeNames[type]}));
                    
                    self.resultContainer.getElementsByTagName('TABLE')[0].appendChild(tr);
                    self.rowCount ++;
                } 
            }
        },
        
        download: function(data, filename, type, linkText, position) 
        {
            var output = "\uFEFF" + data;
            var file = new Blob([output], {type: type});
            if (window.navigator.msSaveOrOpenBlob) // IE10+
                window.navigator.msSaveOrOpenBlob(file, filename);
            else { // Others
                var a = document.createElement("a"),
                    url = URL.createObjectURL(file);
                a.href = url;
                a.download = filename;
                a.className = "ui-btn-primary ui-btn-main";
                a.text = linkText;
                this.downloadLink.querySelector('.'+position).innerHTML = '';
                this.downloadLink.querySelector('.'+position).appendChild(a);
            }
        },
        
        serializeForm: function (form) {

                // Setup our serialized data
                var serialized = {users: {}};

                // Loop through each field in the form
                for (var i = 0; i < form.elements.length; i++) {

                        var field = form.elements[i];

                        // Don't serialize fields without a name, submits, buttons, file and reset inputs, and disabled fields
                        if (!field.name || field.disabled || field.type === 'file' || field.type === 'reset' || field.type === 'submit' || field.type === 'button') continue;

                        if (field.dataset.for) {
                           var dataFor = field.dataset.for;
                            if (typeof serialized.users[dataFor] === "undefined") {
                                serialized.users[dataFor] = {};
                            } 
                            serialized.users[dataFor][field.name] = field.value; 
                        } else {
                            serialized[field.name] = field.value;
                        }
                }
                return serialized;
        },
    };
    
    BX.InnChecker.create = function(settings)
    {
        var self = new BX.InnChecker();
        self.initialize(settings);
        return self;
    };
}
