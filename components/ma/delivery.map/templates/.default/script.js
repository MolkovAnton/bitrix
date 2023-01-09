class DeliveryMap
{
    map;
    params;
    iframe = false;
    deliveryPoint;
    searchControl;
    deliveryZones;
    curentZone = null;
    exportProps;
    address;
    selectedCoords;
    
    constructor(params)
    {
        this.params = params;
        if (this.params.iframe === 'Y') this.iframe = true;
        ymaps.ready(BX.delegate(this.init, this));
    }
    
    init()
    {
        let hasSelectedPoint = this.params.selectedPoint.LAT && this.params.selectedPoint.LON;
        this.map = new ymaps.Map(this.params.container, {
            center: hasSelectedPoint ? [this.params.selectedPoint.LAT, this.params.selectedPoint.LON] : this.params.startPoint,
            zoom: hasSelectedPoint ? 16 : 12,
            controls: ['geolocationControl', 'searchControl']
        });
        this.deliveryPoint = new ymaps.GeoObject({
            geometry: {type: 'Point'},
            properties: {iconCaption: 'Адрес'}
        }, {
            preset: 'islands#redCircleDotIcon',
            draggable: true,
            iconCaptionMaxWidth: '400',
            iconColor: 'black'
        });
        if (hasSelectedPoint) {
            this.deliveryPoint.geometry.setCoordinates([this.params.selectedPoint.LAT, this.params.selectedPoint.LON]);
            this.deliveryPoint.properties.set({
                iconCaption: this.params.selectedPoint.ADDRESS,
                balloonContentHeader: this.params.selectedPoint.ADDRESS
            });
            this.map.center = [this.params.selectedPoint.LAT, this.params.selectedPoint.LON];
            this.map.zoom = 6;
        }
        
        this.searchControl = this.map.controls.get('searchControl');
        this.searchControl.options.set({noPlacemark: true, placeholderContent: this.params.messages['DELIVERY_MAP.SEARCH_PLACEHOLDER']});
        this.map.geoObjects.add(this.deliveryPoint);

        BX.ajax({
            url: this.params.zonesPatch,
            dataType: 'json',
            onsuccess: BX.delegate(this.onZonesLoad, this)
        });
    }
    
    onZonesLoad(json)
    {
        this.deliveryZones = ymaps.geoQuery(json).addToMap(this.map);
        // Задаём цвет и контент балунов полигонов.
        this.deliveryZones.each((obj) => {
            if (obj.properties === null) return;
            obj.options.set({
                fillColor: obj.properties.get('fill'),
                fillOpacity: obj.properties.get('fill-opacity'),
                strokeColor: obj.properties.get('stroke'),
                strokeWidth: obj.properties.get('stroke-width'),
                strokeOpacity: obj.properties.get('stroke-opacity')
            });
            obj.events.add('click', (e) => {
                this.getAddress(e);
            });
            /*let description = obj.properties.get('description');
            if (typeof description !== 'undefined' && description !== null) {
                let zonePosition = description.match(/#(.+)#/);
                if (zonePosition !== null && typeof zonePosition[0] !== 'undefined') {
                    description = description.slice(zonePosition[0].length);
                }
                obj.properties.set('balloonContent', description);
            }*/
        });

        // Проверим попадание результата поиска в одну из зон доставки.
        this.searchControl.events.add('resultshow', (e) => {
            this.highlightResult(this.searchControl.getResultsArray()[e.get('index')]);
        });

        // Проверим попадание метки геолокации в одну из зон доставки.
        this.map.controls.get('geolocationControl').events.add('locationchange', (e) => {
            this.highlightResult(e.get('geoObjects').get(0));
        });

        // При перемещении метки сбрасываем подпись, содержимое балуна и перекрашиваем метку.
        this.deliveryPoint.events.add('dragstart', () => {
            this.deliveryPoint.properties.set({iconCaption: '', balloonContent: ''});
            this.deliveryPoint.options.set('iconColor', 'black');
        });

        // По окончании перемещения метки вызываем функцию выделения зоны доставки.
        this.deliveryPoint.events.add('dragend', () => {
            this.highlightResult(this.deliveryPoint);
        });
        
        this.map.events.add('click', (e) => {
            this.getAddress(e);
        });
    }
    
    highlightResult(obj) {
        // Сохраняем координаты переданного объекта.
        let coords = obj.geometry.getCoordinates();
        this.selectedCoords = coords;
        // Находим полигон, в который входят переданные координаты.
        this.deliveryPoint.geometry.setCoordinates(coords);
        let polygon = this.deliveryZones.searchContaining(coords).get(0);
        if (polygon) {
            let description = polygon.properties.get('description');
            if (description !== null) {
                let zone = description.match(/#(.+)#/);
                this.curentZone = zone !== null ? zone[1] : null;
            }
            // Перемещаем метку с подписью в переданные координаты и перекрашиваем её в цвет полигона.
            this.deliveryPoint.options.set('iconColor', polygon.properties.get('fill'));
        } else {
            this.curentZone = null;
        }
        // Задаем подпись для метки.
        if (typeof(obj.getThoroughfare) === 'function') {
            this.setData(obj);
        } else {
            let self = this;
            ymaps.geocode(coords, {results: 1}).then((res) => {
                let obj = res.geoObjects.get(0);
                self.setData(obj);
            });
        }
    }
    
    setData(obj){
        let address = obj.getAddressLine();
        this.address = address;
        this.deliveryPoint.properties.set({
            iconCaption: address,
            balloonContentHeader: address
        });
        this.exportPropsValues();
    }
    
    exportPropsValues()
    {
        let doc, zoneInput, addressInput, coordsProp, wind;
        doc = this.iframe ? window.top.document : document;
        wind = this.iframe ? window.top : window;
        zoneInput = doc.querySelector('input#'+this.params.exportProps.zone);
        addressInput = doc.querySelector('input#'+this.params.exportProps.address);
        
        if (this.params.coordsProp) {
            let coordPropArr = this.params.coordsProp.split('.');
            coordsProp = wind[coordPropArr.shift()];
            while(coordsProp && coordPropArr.length) coordsProp = coordsProp[coordPropArr.shift()];
            if (typeof coordsProp !== 'undefined') {
                coordsProp.lat = this.selectedCoords[0];
                coordsProp.lon = this.selectedCoords[1];
            }
        }
        
        if (typeof zoneInput !== 'undefined' && zoneInput !== null) zoneInput.value = this.curentZone;
        if (typeof addressInput !== 'undefined' && addressInput !== null) addressInput.value = this.address;
    }
    
    getAddress(e)
    {
        let self = this;
        ymaps.geocode(e.get('coords'), {results: 1}).then(function (res) {
            self.deliveryPoint.properties.set({iconCaption: '', balloonContent: ''});
            self.deliveryPoint.options.set('iconColor', 'black');
            self.highlightResult(res.geoObjects.get(0));
        });
    }
}
