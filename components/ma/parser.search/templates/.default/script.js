if(typeof(BX.ParserSearch) === "undefined")
{
    BX.ParserSearch = function(){
        this.searchButton = null; //Кнопка загрузки файла импорта
        this.searchWordsArea = null; //textarea со списком слов для поиска на странице
        this.inputFileArea = null; //input в котором храниться прикрепляемый файл
        this.inputFile = null; //Прикрепленный файл импорта
        this.fieldChooser = null; //Окно с выбором из какого столбца файла загружать url
        this.importFileFields = []; //Массив с возможными значения имен столбцов из файла импорта
        this.fieldChooserSelect = {}; //Select с вариантами выбора полей для поиска в файле импорта
        this.fieldChooserButton = null; //Кнопка подтверждения выбора полей документа
        this.searchFieldsNames = {}; //Имя колонки по которой будут искаться в файле импорта
        this.parserUrls = {}; //Массив url для прохода парсера
        this.searchWords = []; //Массив слов для поиска
        this.parserUrl = null; //Страница на которой размещен парсер для ajax запросов
        this.importData = {}; //Данные файла импорта
        this.rawInput = {}; //Результат парсинга файла импорта
        this.headerFields = {}; //Список заголовков
        this.saveWordsButton = null; //Кнопка сохранения слов поиска
        this.componentName = ''; //Имя компонента для ajax вызовов
        this.fieldsNamesInLead = {}; //Массив имен полей лида с символьными кодами
        this.assignedName = ''; //Название поля ответственный в лиде
        this.foundAssignedId = null; //Id ответственного в лиде при прохождении фильтра по поиску слов на странице
        this.notFoundAssignedId = null; //Id ответственного в лиде если слова не найдены (по умолчанию)
        this.parserInfo = null; //Контейнер с информацией о готовящихся лидах
        this.infoBody = null; //Текстовая информация о готовящися лидах. Формируется постепенно
        this.infoNextButton = null; //Кнопка продолжения добавления лидов
        this.totalInput = 0; //Всего строк в файле импорта
        this.leadsFound = 0; //Будут созданы лиды для найденных слов на страницах
        this.setResponsibleButton = null; //Кнопка сохранения ответственных
        this.responsibleFoundSelectorId = null; //Контейнер выбора ответственного если слова найдены
        this.responsibleNotFoundSelectorId = null; //Контейнер выбора ответственного если слова не найдены
        this.numLeadsCreated = 0; //Количество созданных лидов
        this.numLeadsNotCreated = 0; //Количество не созданных из за ошибок лидов
        this.curUser = ''; //Текущий пользователь
        this.logPatch = ''; //Путь к файлу лога
        this.fileNameCont = null; //Вывод названия файла
        this.tagFieldName = null; //Имя свойства ТЭГ в лиде
        this.dublSearchFields = {'INN': {}, 'PHONE': {}, 'EMAIL': {}, 'WEB': {}}; //Массив с полями для поиска дубликатов по ИНН, телефону, имейлу
        this.responsiblesFilled = false; //Флаг заполнености ответственных
        this.fmFieldsNames = {}; //Соответствия имен полей из файла с именами свойств лида телефон и имейл
        this.outputFile = ''; //Строку для экспорта в csv
    };
    
    BX.ParserSearch.prototype = 
    {
        //Первая инициализация
        initialize: function(settings)
        {
            this.searchButton = BX(settings.searchButton);
            this.searchWordsArea = BX(settings.searchWordsArea);
            this.inputFileArea = BX(settings.inputFileArea);
            this.fieldChooser = BX(settings.fieldChooser);
            this.fieldChooserSelect.url = this.fieldChooser.querySelector('[data-field="url"]');
            this.fieldChooserSelect.inn = this.fieldChooser.querySelector('[data-field="inn"]');
            this.fieldChooserButton = this.fieldChooser.querySelector('[class="ui-btn-primary ui-btn-main"]');
            if (settings.parserUrl) this.parserUrl = settings.parserUrl;
            this.saveWordsButton = BX(settings.saveWordsButton);
            this.componentName = settings.componentName;
            this.fieldsNamesInLead = settings.fieldsNamesInLead;
            this.assignedName = settings.assignedName;
            this.foundAssignedId = settings.foundAssignedId;
            this.notFoundAssignedId = settings.notFoundAssignedId;
            this.parserInfo = BX(settings.parserInfo);
            this.infoBody = this.parserInfo.getElementsByClassName('parser_info_body')[0];
            this.infoNextButton = this.parserInfo !== null ? this.parserInfo.querySelector('[data-role="button"]') : null;
            this.setResponsibleButton = BX(settings.setResponsibleButton);
            this.responsibleFoundSelectorId = settings.responsibleFoundSelectorId;
            this.responsibleNotFoundSelectorId = settings.responsibleNotFoundSelectorId;
            this.curUser = settings.curUser;
            this.logPatch = settings.logPatch;
            this.fileNameCont = this.searchButton.parentElement.getElementsByClassName('file_name')[0];
            this.tagFieldName = settings.tagFieldName;
            this.responsiblesFilled = settings.responsiblesFilled;
            this.fmFieldsNames = settings.fmFieldsNames;

            if (this.searchWordsArea !== null && typeof this.searchWordsArea.value.split === 'function') this.searchWords = this.searchWordsArea.value.split(',');
            this.searchWords.forEach((val, index, array) => array[index] = val.trim());
           
            //Listeners
            this.searchButton.addEventListener('click', BX.delegate(this.onSearchButtonClick, this));
            this.inputFileArea.addEventListener('change', BX.delegate(this.onFileInput, this)); 
            if (this.searchWordsArea !== null) this.searchWordsArea.addEventListener('change', BX.delegate(this.onSearchWordsAreaChange, this));
            if (this.saveWordsButton !== null) this.saveWordsButton.addEventListener('click', BX.delegate(this.onSaveWordsButtonClick, this));
            this.fieldChooserButton.addEventListener('click', BX.delegate(this.onFieldChooserButtonClick, this));
            this.infoNextButton.addEventListener('click', BX.delegate(this.onInfoNextButtonClick, this));
            if (this.setResponsibleButton !== null) this.setResponsibleButton.addEventListener('click', BX.delegate(this.onSetResponsibleButtonClick, this));
           
            for (var field in this.fieldChooserSelect) {
                this.fieldChooserSelect[field].addEventListener('change', BX.delegate(this.onFieldChooserSelect, this));
            }
            
            BX.addCustomEvent('BX.Main.User.SelectorController:select', BX.proxy(this.setNewResponsibles, this));
        },
        
        //Очистка данных при изменении входных параметров
        //Если передано в параметре не true очищает в том числе и данные разбора csv файла
        clearData: function(all = true)
        {
            if (all === true) {
                this.importData = {};
                this.importFileFields = [];
                this.rawInput = {};
                this.headerFields = {};
            }
            this.parserUrls = [];
            this.dublSearchFields = {'INN': {}, 'PHONE': {}, 'EMAIL': {}, 'WEB': {}};
            this.totalInput = 0;
            this.leadsFound = 0;
            this.infoNextButton.classList.remove('hidden');
            this.infoNextButton.dataset.stage = 1;
            this.parserInfo.classList.add('hidden');
            this.infoBody.innerHTML = '';
            this.numLeadsCreated = 0;
            this.numLeadsNotCreated = 0;
            this.outputFile = '';
        },

        //События по клику на кнопку импорта файла
        onSearchButtonClick: function(e)
        { 
            //Генерирует событие по клику на скрытый input в котором выбирается файл импорта
            this.inputFileArea.click();
        },
        
        //Событие возникает при импорте файла
        //Записывает файл в переменную и разбирает его первую строку с названиями полей для формирования выбора соответствия полей
        onFileInput: function (e)
        {
            this.clearData();
            this.inputFile = e.target.files[0];
            var i = 0;
            
            Papa.parse(BX.ParserSearch.inputFile, {
                header: true,
                encoding: "Windows-1251",
                skipEmptyLines: true,
                dynamicTyping: true,
                delimiter: ";",
                transformHeader: function(header) {
                    BX.ParserSearch.importFileFields.push(header);
                    BX.ParserSearch.headerFields[header] = i;
                    i++;
                },
                preview: 1,
                complete: function() {
                    BX.ParserSearch.addToLog(new Date().toLocaleString('ru')+" - "+BX.ParserSearch.curUser+" загрузил файл на проверку\r\n");
                    BX.ParserSearch.showFieldChooser();
                    BX.ParserSearch.fileNameCont.textContent = BX.ParserSearch.inputFile.name;
                    BX.ParserSearch.putDataToFile(BX.ParserSearch.importFileFields);
                }
            });
        },
        
        //Событие возникает при выборе значений полей из файла импорта в которых будет проходить поиск ИНН и сайтов
        onFieldChooserSelect: function(e)
        {
            this.searchFieldsNames[e.target.dataset.field] = e.target.value;
        },
        
        //Событие возникает при нажатии на кнопку подтверждения соответствия полей
        //Сначала очищает данные на случай если для уже загруженного файла были выбраны другие поля
        onFieldChooserButtonClick: function()
        {
            this.clearData(false);
            this.putDataToFile(this.importFileFields);
            this.makeUrlArray();
        },
        
        //Событие возникает при изменении содержимого поля со списком слов для поиска - записывает новые слова в переменную
        onSearchWordsAreaChange: function(e)
        {
            if (typeof e.target.value.split === 'function') this.searchWords = e.target.value.split(',');
            this.searchWords.forEach((val, index, array) => array[index] = val.trim());
        },
        
        //Событие по клику на кнопку Сохранить списка слов поиска - делает ajax запрос на сохранение списка слов
        onSaveWordsButtonClick: function()
        {
            BX.ajax.runComponentAction(this.componentName, 'setParameters', {
                mode: 'class',
                data: {
                    post: {
                        WORDS: BX.ParserSearch.searchWords.join(', '),
                    }
                },
            })
            .then(function(response) {
                console.log(response);
            });
        },
        
        //Событие по клику на кнопку Продолжить
        onInfoNextButtonClick: function()
        {
            //Параметр стадии обработки - берется из data кнопки
            var stage = parseInt(this.infoNextButton.dataset.stage);
            
            switch(stage) {
                case 1:
                    //На первой стадии происходит обращение к скрипту поиска слов
                    //По окончании обработки - показывает информационное сообщение сколько лидов будет создано по ответственным
                    this.ajaxSearch().then(()=>{
                        this.showInfo(this.infoAdd(2));
                        this.infoNextButton.dataset.stage = stage + 1;
                    });
                    break;
                case 2:
                    //На второй стадии запускает скрипт создания лидов и скрывает кнопку - финальная стадия
                    this.infoNextButton.classList.add('hidden');
                    this.addToLog(this.curUser+" подтвердил импорт данных\r\n");
                    this.createLeads();
                    break;
            }
        },
        
        //Событие по кнопке Сохранить ответственных - делает ajax запрос к функции сохранения ответственных
        onSetResponsibleButtonClick: function()
        {
            BX.ajax.runComponentAction(this.componentName, 'setParameters', {
                mode: 'class',
                data: {
                    post: {
                        RESPONSIBLE_FOR_FOUND_WORDS_LEAD: BX.ParserSearch.foundAssignedId.ID,
                        RESPONSIBLE_FOR_NOT_FOUND_WORDS_LEAD: BX.ParserSearch.notFoundAssignedId.ID
                    }
                }
            })
            .then(function(response) {
                console.log(response);
                if (!BX.ParserSearch.responsiblesFilled) document.location.reload();
            });
        },
        
        //Событие возникает при изменении ответственного - проверяет тип события и записывает данные в соответствующую переменную
        setNewResponsibles: function(data)
        {
            switch (data.selectorId) {
                case this.responsibleFoundSelectorId:
                    this.foundAssignedId = {ID: data.item.entityId, NAME: data.item.name};
                    break;
                case this.responsibleNotFoundSelectorId:
                    this.notFoundAssignedId = {ID: data.item.entityId, NAME: data.item.name};
                    break;
            }
        },
        
        //Формирует блок с выбором соответствия полей ИНН и сайт
        showFieldChooser: function()
        {
            for (var field in this.fieldChooserSelect) {
                this.fieldChooserSelect[field].innerHTML = '';
                this.searchFieldName = null;
                this.fieldChooserSelect[field].appendChild(BX.create('OPTION', {
                    text: '',
                    attrs: {
                        value: 'none',
                        selected: 1,
                        disabled: 1,
                        hidden: 1,
                    }
                }));
                for (var i = 0; i<this.importFileFields.length; i++) {
                    this.fieldChooserSelect[field].appendChild(BX.create('OPTION', {
                        text: this.importFileFields[i],
                        attrs: {
                            value: this.importFileFields[i],
                        }
                    }));
                }
            }
            this.fieldChooser.classList.remove('hidden');
        },
        
        //Разбирает данные из файла импорта библиотекой Papa
        makeUrlArray: function()
        {
            this.parserUrls = {};
            
            Papa.parse(this.inputFile, {
                header: true,
                encoding: "Windows-1251",
                skipEmptyLines: true,
                dynamicTyping: true,
                delimiter: ";",
                chunk: function(results, parser) {
                    //Информацию получает кусками
                    //Заполняет массив url адресов для проверки (при прохождении проверки на валидность) и массив с ИНН
                    for (var i in results.data) {
                        BX.ParserSearch.rawInput[i] = results.data[i]; //Заполняем массив непреобразованных данных из файла импорта
                        if (BX.ParserSearch.isValidURL(results.data[i][BX.ParserSearch.searchFieldsNames.url])) {
                            BX.ParserSearch.parserUrls[i] = results.data[i][BX.ParserSearch.searchFieldsNames.url];
                            BX.ParserSearch.dublSearchFields['WEB'][i] = results.data[i][BX.ParserSearch.searchFieldsNames.url];
                        } 
                        if (results.data[i][BX.ParserSearch.searchFieldsNames.inn] !== null) {
                            BX.ParserSearch.dublSearchFields['INN'][i] = results.data[i][BX.ParserSearch.searchFieldsNames.inn];
                            for (var rus in results.data[i]) {
                                if (BX.ParserSearch.fmFieldsNames.PHONE.includes(rus)) {
                                    if (typeof BX.ParserSearch.dublSearchFields['PHONE'][i] === 'undefined') {
                                        BX.ParserSearch.dublSearchFields['PHONE'][i] = [results.data[i][rus]];
                                    } else {
                                        BX.ParserSearch.dublSearchFields['PHONE'][i].push(results.data[i][rus]);
                                    }
                                } else if (BX.ParserSearch.fmFieldsNames.EMAIL.includes(rus)) {
                                    if (typeof BX.ParserSearch.dublSearchFields['EMAIL'][i] === 'undefined') {
                                        BX.ParserSearch.dublSearchFields['EMAIL'][i] = [results.data[i][rus]];
                                    } else {
                                        BX.ParserSearch.dublSearchFields['EMAIL'][i].push(results.data[i][rus]);
                                    }
                                }
                            }
                        }
                        
                        //Преобразует данные из файла импорта в соответствии с картой имен "Имя поля на русском" => "Код свойства в лиде"
                        //Если такого поля не найденно - отбрасывает такие данные импорта
                        var namedData = BX.ParserSearch.makeNamedData(results.data[i]);
                        namedData[BX.ParserSearch.assignedName] = BX.ParserSearch.notFoundAssignedId.ID;
                        BX.ParserSearch.importData[i] = namedData;
                    }
                },
                complete: function(){
                    //По окончании парсинга csv файла запускает поиск по ИНН
                    BX.ParserSearch.totalInput = Object.getOwnPropertyNames(BX.ParserSearch.importData).length;
                    BX.ParserSearch.checkInn()
                        .then(resp => BX.ParserSearch.showInfo(resp));
                }
            });
        },
        
        //Формирует строку для записи в файл экспорта
        putDataToFile: function(data)
        {
            var str = '';
            var dataAr = [];
            
            if (typeof data === 'object') {
                if (typeof data.forEach === 'function') {
                    dataAr = data;
                } else {
                    for (var field in data) {
                        if (data.hasOwnProperty(field)) {
                            dataAr[this.headerFields[field]] = data[field];
                        }
                    }
                }   
            } else {
                return;
            }

            dataAr.forEach(item => {
                str += item;
                str += ";";
            });
            str += "\r\n";

            this.outputFile += str;
        },
        
        //Формирует ссылку для скачивания файла экспорта
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
                this.fieldChooser.querySelector('.'+position).innerHTML = '';
                this.fieldChooser.querySelector('.'+position).appendChild(a);
            }
        },
        
        //Функция добавляет информацию к блоку информации
        showInfo: function(add)
        {
            if (this.parserInfo.classList.contains('hidden')) this.parserInfo.classList.remove('hidden');
            
            if (typeof add !== 'undifined' && add !== null)
                this.infoBody.appendChild(add);
        },
        
        //Формирует информационное сообщение в соответствии с переданной стадией
        infoAdd: function(stage)
        {
            if (!stage) return;
            
            switch(stage) {
                case 1:
                    return BX.create('DIV', 
                        {
                            children: [
                                BX.create('DIV', {
                                    attrs: {className: 'text_left'},
                                    children: [
                                        BX.create('SPAN', {text: `Всего строк в файле импорта: ${this.totalInput}`}),
                                        BX.create('BR'),
                                        BX.create('SPAN', {text: `Проверенно ИНН: ${this.totalInput}`}),
                                        BX.create('BR'),
                                        BX.create('SPAN', {text: `из них найдено в CRM: ${this.totalInput - Object.getOwnPropertyNames(this.importData).length}`}),
                                        BX.create('BR'),
                                        BX.create('SPAN', {text: `из них не найдено в CRM: ${Object.getOwnPropertyNames(this.importData).length}`}),
                                        BX.create('BR'),
                                        BX.create('SPAN', {text: `Будет проверено сайтов: ${Object.getOwnPropertyNames(this.parserUrls).length}`}),
                                    ]
                                })
                            ]
                        }
                    );
                case 2:
                    return BX.create('DIV', {
                        children: [
                            BX.create('DIV', {attrs: {className: 'delimiter text_left'}, text: '---------------------------------------------------'}),
                            BX.create('DIV', {
                                attrs: {className: 'text_left'},
                                text: `В результате проверки адресов будут созданы лиды - на ${this.foundAssignedId.NAME}: ${this.leadsFound} | на ${this.notFoundAssignedId.NAME}: ${Object.getOwnPropertyNames(this.importData).length - this.leadsFound}`
                            })
                        ]
                    });
                case 3:
                    return BX.create('DIV', {
                        children: [
                            BX.create('DIV', {attrs: {className: 'delimiter'}, text: '---------------------------------------------------'}),
                            BX.create('DIV', {
                                text: `Создано - ${this.numLeadsCreated} лидов. Не созданно из за ошибок - ${this.numLeadsNotCreated}`
                            })
                        ]
                    });
                default:
                    return null;
            }
        },
        
        //Проверка url на валидность
        isValidURL: function(string) {
            if (typeof string !== 'undefined' && string !== null && typeof string.match === 'function') {
                var res = string.match(/(http(s)?:\/\/.)?(www\.)?[-a-zA-Z0-9@:%._\+~#=]{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#?&//=]*)/g);
                return (res !== null);  
            } else {
                return false;
            }
        },
        
        //Формирует массив соответствий имен полей из файла с кодами свойств в лиде (передается из компонента)
        //Учитывает вложенные массивы (до 10 вложенности)
        makeNamedData: function(data)
        {
            var result = {};
            for (let [key, value] of Object.entries(data)) {
                if (typeof value === 'undefined' || value === null) continue;
                if (typeof this.fieldsNamesInLead.STRING[key] !== 'undefined') {
                    if (typeof this.fieldsNamesInLead.STRING[key] === 'object') {
                        let count = 0;
                        let curKey = this.fieldsNamesInLead.STRING[key];
                        let curField = result;
                        while (typeof curKey === 'object' && count<10) {
                            count ++;
                            for (let [inKey, inVal] of Object.entries(curKey)) {
                                if (typeof inVal !== 'object') {
                                    curField[inKey] = inVal;
                                    curField['VALUE'] = value;
                                    curKey = false;
                                } else {
                                    curKey = inVal;
                                    if (typeof curField[inKey] === 'undefined') curField[inKey] = {};
                                    curField = curField[inKey]; 
                                }
                            }
                        }
                    } else {
                        result[this.fieldsNamesInLead.STRING[key]] = value;
                    }
                } else if (typeof this.fieldsNamesInLead.ENUM[key] !== 'undefined' && typeof this.fieldsNamesInLead.ENUM[key]['VALUES'][value] !== 'undefined') {
                    var fieldVal = null;
                    
                    if (this.fieldsNamesInLead.ENUM[key]['NOT_ARRAY']) {
                        fieldVal = this.fieldsNamesInLead.ENUM[key]['VALUES'][value]['ID'];
                    } else {
                        fieldVal = [this.fieldsNamesInLead.ENUM[key]['VALUES'][value]['ID']];
                    }
                    
                    result[this.fieldsNamesInLead.ENUM[key]['NAME']] = fieldVal;
                } 
                if (this.tagFieldName !== '' && this.tagFieldName !== null) {
                    result[this.tagFieldName] = 'Бегемот';
                }
            }
            return result;
        },

        //Ajax запрос на поиск слов на страницах
        ajaxSearch: function ()
        {
            return new Promise((resolve)=>{
                
                var partSize = 10;
                var partNum = 0;
                var counter = 0;
                var urls = {};
                var checkedUrls = {
                    find: {},
                    notFind: {}
                };
                var maxCounter = Object.getOwnPropertyNames(BX.ParserSearch.parserUrls).length;
                var maxSize = maxCounter/partSize;
                
                //Рассчитывает количество запросов в соответствии с количеством адресов передоваемых на проверку в одном запросе (partSize)
                if (maxCounter === 0) resolve();
                
                for (var i in BX.ParserSearch.parserUrls) {
                    if (BX.ParserSearch.parserUrls.hasOwnProperty(i)) {
                        counter++;
                        urls[i] = BX.ParserSearch.parserUrls[i];
                        if (counter%partSize === 0 || counter === maxCounter) {
                            //Формирует запросы с определенным количеством адресов для проверки и отправляет их
                            BX.ajax({
                                url: BX.ParserSearch.parserUrl,
                                method: "POST",
                                dataType: "json",
                                data: {
                                   sessid: BX.bitrix_sessid(),
                                   URLS: urls,
                                   WORDS: BX.ParserSearch.searchWords,
                                   REQUIRED_WORDS_COUNT: 2,
                                },
                                onsuccess: function(res) {
                                    partNum++;
                                    if (typeof res !== 'undefined' && typeof res['DATA'] !== 'undefined' && typeof res['DATA'][Symbol.iterator] === 'function' && res['DATA'] != null) {
                                        for (var i of res['DATA']) {
                                            BX.ParserSearch.importData[i][BX.ParserSearch.assignedName] = BX.ParserSearch.foundAssignedId.ID;
                                            BX.ParserSearch.leadsFound ++; 
                                            if (BX.ParserSearch.tagFieldName !== '' && BX.ParserSearch.tagFieldName !== null) {
                                                BX.ParserSearch.importData[i][BX.ParserSearch.tagFieldName] = 'Бегемот целевой';
                                            }
                                        }
                                    }
                                    
                                    if (partNum >= maxSize)
                                        resolve();
                                },
                            });
                            urls = {};
                        } 
                    }
                }
            });
        },
        
        //Ajax запрос на проверку ИНН
        checkInn: function()
        {
            return new Promise((resolve)=>{
                BX.ajax.runComponentAction(this.componentName, 'getAbsent', {
                    mode: 'class',
                    data: {
                        post: {
                            DATA: BX.ParserSearch.dublSearchFields, 
                            ACTION: 'search_inn',
                            LOG_PATH: this.logPatch
                        }
                    },
                })
                .then(function(response) {
                    //Возвращаются найденные ИНН - для них не нужно проверять сайты и создавать лиды, они удаляются из массивов данных
                    for (var i in response.data) {
                        BX.ParserSearch.putDataToFile(BX.ParserSearch.rawInput[response.data[i]]); //Наполняем файл данными для экспорта
                        delete BX.ParserSearch.parserUrls[response.data[i]];
                        delete BX.ParserSearch.importData[response.data[i]];
                    }
                    if (response.data.length > 0) {
                        BX.ParserSearch.download(BX.ParserSearch.outputFile, 'output.csv', 'text/csv;charset=Windows-1251', "Скачать найденные", 'export');
                    }
                    //По окончании работы формирует информационное сообщение
                    resolve(BX.ParserSearch.infoAdd(1));
                }, (response) => {
                    console.dir(response);
                });
            });
            
        },
        
        //Ajax запрос на создание лидов
        createLeads: function()
        {
            BX.ajax.runComponentAction(this.componentName, 'createLeads', {
                mode: 'class',
                data: {
                    post: {
                        ITEMS: BX.ParserSearch.importData
                    }
                }
            })
            .then(function(response) {
                console.log(response);
                BX.ParserSearch.numLeadsCreated = response.data.CREATED.length ? response.data.CREATED.length : 0;
                BX.ParserSearch.numLeadsNotCreated = typeof response.data.NOT_CREATED !== 'undefind' ? response.data.NOT_CREATED : 0;
                BX.ParserSearch.showInfo(BX.ParserSearch.infoAdd(3));
                BX.ParserSearch.addToLog("-------------------------------------\r\n\r\n");
            });
        },
        
        //Ajax запрос на добавление строки в лог файл
        addToLog: function(mes)
        {
            BX.ajax.runComponentAction(this.componentName, 'addToLog', {
                mode: 'class',
                data: {
                    post: {
                        MESSAGE: mes,
                        LOG_PATH: this.logPatch
                    }
                }
            })
            .then(function(response) {
                console.log(response.data ? 'запись добавлена в лог' : 'запись не добавлена в лог');
            });
        }      
    };
    
    //Инициализирует синглтон
    BX.ParserSearch.create = function(settings)
    {
        var self = new BX.ParserSearch();
        self.initialize(settings);
        return self;
    };
}
