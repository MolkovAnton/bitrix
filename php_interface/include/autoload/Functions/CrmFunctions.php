<? 
namespace Functions;

use \Bitrix\Main\Loader,
    \Bitrix\Crm\EntityAddress,
    \Bitrix\Main\Entity\ReferenceField,
    \Bitrix\Crm\AddressTable,
    \Bitrix\Crm\EntityAddressType,
    \Bitrix\Location\Entity\Address,
    \Bitrix\Sale\Location\LocationTable;

class CrmFunctions
{
    public function getRegionFromCity(string $city) {
        Loader::includeModule('sale');
        
        $locMap = [
            1 => 'COUNTRY',
            4 => 'PROVINCE',
            2 => 'REGION'
        ];
        $res = LocationTable::getList(array(
            'filter' => array(
                '=TYPE.CODE' => 'CITY',
                'NAME.NAME' => $city,
                'REGION.LANGUAGE_ID' => 'ru',
                'PARENTS.TYPE_ID' => [1, 2, 4]
            ),
            'select' => array(
                'ID',
                'LNAME' => 'NAME.NAME',
                'SHORT_NAME' => 'NAME.SHORT_NAME',
                'REGION_ID',
                'REGION',
                'CNT',
                'PARENTS',
            ),
            'runtime' => [
                new ReferenceField('REGION', 'Bitrix\Sale\Location\Name\Location', array(
                    'LOGIC' => 'AND',
                    '=this.PARENTS.ID' => 'ref.LOCATION_ID',
                )),
            ],
        ));
        $res->addReplacedAliases(array('LNAME' => 'NAME'));

        $fullLocation = [];
        while ($loc = $res->fetch()) {
            if(intval($loc['CNT']) === 1) {
                $loctType = $locMap[$loc['SALE_LOCATION_LOCATION_PARENTS_TYPE_ID']];
                $fullLocation[$loctType] = $loc['SALE_LOCATION_LOCATION_REGION_NAME'];
            }
        }
        
        return $fullLocation;
    }
    
    public function addRegionToAddress(array $param) {
        $typeID = $param['TYPE_ID'] ?: EntityAddressType::Primary;
        $entityTypeID = $param['ENTITY_TYPE_ID'];
        $entityID = $param['ENTITY_ID'];
        
        if(empty($typeID) || empty($entityTypeID) || empty($entityID)) return;
        
        $isContactCompanyCompatibility = ($entityTypeID === \CCrmOwnerType::Company
			|| $entityTypeID === \CCrmOwnerType::Contact);
        
        if ($isContactCompanyCompatibility)
		{
			$res = AddressTable::getList(
				[
					'filter' => [
						'=TYPE_ID' => $typeID,
						'=ENTITY_TYPE_ID' => \CCrmOwnerType::Requisite,
						'=ANCHOR_TYPE_ID' => $entityTypeID,
						'=ANCHOR_ID' => $entityID,
						'=IS_DEF' => 1
					],
					'limit' => 1
				]
			);
		}
		else
		{
			$res = AddressTable::getList(
				[
					'filter' => [
						'=TYPE_ID' => $typeID,
						'=ENTITY_TYPE_ID' => $entityTypeID,
						'=ENTITY_ID' => $entityID
					]
				]
			);
		}
        if ($row = $res->fetch())
		{
            $curValues = [
                'TYPE_ID' => $row['TYPE_ID'],
                'ENTITY_TYPE_ID' => $row['ENTITY_TYPE_ID'],
                'ENTITY_ID' => $row['ENTITY_ID'],
                'ANCHOR_TYPE_ID' => $row['ANCHOR_TYPE_ID'],
                'ANCHOR_ID' => $row['ANCHOR_ID'],
                'ADDRESS_1' => $row['ADDRESS_1'],
                'ADDRESS_2' => $row['ADDRESS_2'],
                'CITY' => $row['CITY'],
                'POSTAL_CODE' => $row['POSTAL_CODE'],
                'REGION' => $row['REGION'],
                'PROVINCE' => $row['PROVINCE'],
                'COUNTRY' => $row['COUNTRY'],
                'COUNTRY_CODE' => $row['COUNTRY_CODE'],
                'LOC_ADDR_ID' => (int)$row['LOC_ADDR_ID'],
                'IS_DEF' => $row['IS_DEF']
			];
		}
        
        if ((!empty($curValues['REGION']) && !empty($curValues['PROVINCE'])) || empty($curValues['CITY'])) return;
        
        $region = self::getRegionFromCity($curValues['CITY']);
        foreach ($region as $code => $value) {
            if (empty($curValues[$code]))
                $curValues[$code] = $value;
        }
        
        if ($isContactCompanyCompatibility)
        {
            $curValues = EntityAddress::applyCompatibility($curValues);
        }
        
        Loader::includeModule('location');
        $locationAddress = Address::load($curValues['LOC_ADDR_ID']);
        $addressFieldMap = [
            'ADDRESS_1' => Address\FieldType::ADDRESS_LINE_1,
			'ADDRESS_2' => Address\FieldType::ADDRESS_LINE_2,
			'CITY' => Address\FieldType::LOCALITY,
			'POSTAL_CODE' => Address\FieldType::POSTAL_CODE,
			'PROVINCE' => Address\FieldType::ADM_LEVEL_1,
			'REGION' => Address\FieldType::ADM_LEVEL_2,
			'COUNTRY' => Address\FieldType::COUNTRY
        ];
        if ($locationAddress instanceof Address) {
            foreach ($addressFieldMap as $crmAddressFieldName => $locationAddressFieldId)
            {
                if (!empty($curValues[$crmAddressFieldName]))
                {
                    $locationFieldValue = $locationAddress->getFieldValue($locationAddressFieldId);

                    if (empty($locationFieldValue) && $curValues[$crmAddressFieldName] !== $locationFieldValue)
                    {
                        $locationAddress->setFieldValue($locationAddressFieldId, $curValues[$crmAddressFieldName]);
                    }
                }
            }

            $locationAddress->save();
        }
		
        AddressTable::upsert($curValues);
    }
}