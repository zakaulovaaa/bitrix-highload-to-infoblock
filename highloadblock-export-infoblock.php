<?php
$_SERVER["DOCUMENT_ROOT"] = "/home/bitrix/www";

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Highloadblock as HL;

const IBLOCK_ID = 6;
const HLBLOCK_ID = 4;
const FINAL_MESSAGE = "...........／＞　 フ.....................\n　　　　　| 　_　 _|\n　 　　　／`ミ _x 彡\n" .
    "　　 　 /　　　 　 |\n　　　 /　 ヽ　　 ﾉ\n　／￣|　　 |　|　|\n　| (￣ヽ＿_ヽ_)_)\n　＼二つ\n";


try {
    Loader::includeModule("highloadblock");
} catch (\Bitrix\Main\LoaderException $e) {
    print_r($e->getMessage());
    return;
}


class HighloadblockHelper
{
    public static function getArrayValuesUserFieldTypeListByName(string $userFieldName): array
    {
        $values = [];
        $rsEnum = CUserFieldEnum::GetList(array(), array("USER_FIELD_NAME" => $userFieldName));
        while ($arEnum = $rsEnum->GetNext()) {
            $values[$arEnum["ID"]] = [
                "ID" => $arEnum["ID"],
                "VALUE" => $arEnum["VALUE"],
                "XML_ID" => $arEnum["XML_ID"]
            ];
        }
        return $values;
    }

    public static function getArrayDataByIdHL(int $idHL): array
    {
        $hlblock = HL\HighloadBlockTable::getById($idHL)->fetch();
        try {
            $entity = HL\HighloadBlockTable::compileEntity($hlblock);
        } catch (\Bitrix\Main\SystemException $e) {
            print_r($e->getMessage());
            return [];
        }
        $entityData = $entity->getDataClass();

        try {
            $rsData = $entityData::getList(array(
                "select" => array("*"),
                "order" => array("ID" => "ASC"),
                "filter" => array("UF_ACTIVE" => "Y")  // Задаем параметры фильтра выборки
            ));
        } catch (\Bitrix\Main\ArgumentException $e) {
            print_r($e->getMessage());
            return [];
        }

        $infoHL = [];
        while ($arData = $rsData->Fetch()) {
            $infoHL[$arData["UF_CODE"]] = $arData;
            $infoHL[$arData["UF_CODE"]]['isOverwritten'] = false;
        }
        return $infoHL;
    }
}


class IBlockHelper
{
    public static function getArrayPropertyByIBlockId(int $iblockId): array
    {
        $properties = [];
        $resProperties = CIBlock::GetProperties($iblockId, array(), array());
        while ($property = $resProperties->Fetch()) {
            $properties[$property["CODE"]] = $property["ID"];
        }
        return $properties;
    }

    public static function getArrayPropertyEnum(int $iblockId, string $propertyName): array
    {
        $enumFields = [];
        $arFilter = [
            "IBLOCK_ID" => $iblockId,
            "CODE" => $propertyName
        ];
        $propertyEnums = CIBlockPropertyEnum::GetList(array(), $arFilter);
        while ($enumField = $propertyEnums->GetNext()) {
            $enumFields[$enumField["XML_ID"]] = [
                "ID" => $enumField["ID"],
                "VALUE" => $enumField["VALUE"],
            ];
        }
        return $enumFields;
    }

    public static function addPropertyEnum(string $propertyId, string $xmlId, string $value): array
    {
        $valueId = (new CIBlockPropertyEnum())::Add([
            'PROPERTY_ID' => $propertyId,
            'VALUE' => $value,
            'XML_ID' => $xmlId,
        ]);
        if ((int)$valueId < 0) {
            throw new \Exception('Unable to add a value in property enum for code = ' . $xmlId);
        }
        return [
            "ID" => $valueId,
            "VALUE" => $value,
            "XML_ID" => $xmlId
        ];
    }

    public static function getIdPropertyEnumElem(array &$propertyEnumByIBlock, string $xmlCode, string $value,
                                                 string $propertyId)
    {
        if (isset($propertyEnumByIBlock[$xmlCode])) {
            $elemId = $propertyEnumByIBlock[$xmlCode]["ID"];
        } else {
            try {
                $newEnum = self::addPropertyEnum($propertyId, $xmlCode, $value);
                $propertyEnumByIBlock[$newEnum["XML_ID"]] = [
                    "ID" => $newEnum['ID'],
                    "VALUE" => $newEnum["VALUE"]
                ];
                $elemId = $newEnum['ID'];
            } catch (Exception $e) {
                print_r($e->getMessage() . "\n");

                return "";
            }
        }
        return $elemId;
    }
}

function getArrayNewValueProperties(array &$enumPropertyCities, array &$codesByProperty, array $params,
                                    array $elemHL, array $properties): array
{
    $cityId = "";

    if (isset($elemHL["UF_CITY"], $params["CITY"]["XML_ID"], $params["CITY"]["VALUE"]) && $elemHL["UF_CITY"] !== "") {
        $cityId = IBlockHelper::getIdPropertyEnumElem($enumPropertyCities, $params["CITY"]["XML_ID"],
            $params["CITY"]["VALUE"], $params["CITY"]["ID_PROPERTY"]);
    }

    $codeId = IBlockHelper::getIdPropertyEnumElem($codesByProperty, $params["CODES"]["XML_ID"],
        $params["CODES"]["VALUE"], $params["CODES"]["ID_PROPERTY"]);

    return [
        $properties["ACTIVE"] => $elemHL["UF_ACTIVE"],
        $properties["CODE"] => $elemHL["UF_CODE"],
        $properties["PRICE"] => $elemHL["UF_PRICE"],
        $properties["FILE"] => $elemHL["UF_FILE"],
        $properties["CITY"] => $cityId,
        $properties["CODES"] => $codeId,
    ];
}

// список городов, описанных в поле highload-блока
$cities = HighloadblockHelper::getArrayValuesUserFieldTypeListByName("UF_CITY");

// информация из highload-блока
$infoHL = HighloadblockHelper::getArrayDataByIdHL(HLBLOCK_ID);

// свойства из инфоблока
$properties = IBlockHelper::getArrayPropertyByIBlockId(IBLOCK_ID);

// список городов из свойства инфоблока
$enumPropertyCities = IBlockHelper::getArrayPropertyEnum(IBLOCK_ID, "CITY");

$codes = array_keys($infoHL);

$arSelect = array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*");
$arFilter = array("IBLOCK_ID" => IBLOCK_ID, 'PROPERTY_CODE' => $codes);

$iblockElements = CIBlockElement::GetList(array(), $arFilter, false, array("nPageSize" => 50), $arSelect);
$codesByProperty = IBlockHelper::getArrayPropertyEnum(IBLOCK_ID, "CODES");

echo "Начинаем замену значений существующих элементов с одинаковым кодом \n";

while ($ob = $iblockElements->GetNextElement()) {
    $arFields = $ob->GetFields();
    $arProps = $ob->GetProperties();
    $elemHL = &$infoHL[$arProps["CODE"]["VALUE"]];

    $params = [
        "CITY" => [
            "XML_ID" => $cities[$elemHL["UF_CITY"]]["XML_ID"],
            "VALUE" => $cities[$elemHL["UF_CITY"]]["VALUE"],
            "ID_PROPERTY" => $properties["CITY"]
        ],
        "CODES" => [
            "XML_ID" => $elemHL["UF_CODE"],
            "VALUE" => $elemHL["UF_CODE"],
            "ID_PROPERTY" => $properties["CODES"]
        ],
    ];

    $arrNewValueProperties = getArrayNewValueProperties($enumPropertyCities, $codesByProperty, $params,
        $elemHL, $properties);

    $elem = (new CIBlockElement)->Update(
        $arFields['ID'],
        [
            "NAME" => "4",
            "PROPERTY_VALUES" => $arrNewValueProperties
        ]
    );

    echo "Замена значений элемента с кодом " . $elemHL["UF_CODE"] . " прошло успешно\n";
    $elemHL['isOverwritten'] = true;
}

echo "Закончили замену значений существующих элементов с одинаковым кодом\n\n";

echo "Начинаем добавление новых элементов \n";

foreach ($infoHL as $elemHL) {
    if ($elemHL["isOverwritten"]) {
        continue;
    }

    $params = [
        "CITY" => [
            "XML_ID" => $cities[$elemHL["UF_CITY"]]["XML_ID"],
            "VALUE" => $cities[$elemHL["UF_CITY"]]["VALUE"],
            "ID_PROPERTY" => $properties["CITY"]
        ],
        "CODES" => [
            "XML_ID" => $elemHL["UF_CODE"],
            "VALUE" => $elemHL["UF_CODE"],
            "ID_PROPERTY" => $properties["CODES"]
        ],
    ];


    $arrNewValueProperties = getArrayNewValueProperties($enumPropertyCities, $codesByProperty, $params,
        $elemHL, $properties);

    $newElem = (new CIBlockElement())->Add(
        [
            "IBLOCK_ID" => IBLOCK_ID,
            "IBLOCK_SECTION_ID" => false,
            "PROPERTY_VALUES"=> $arrNewValueProperties,
            "NAME" => "-",
        ]
    );

    echo "Добавление элемента с кодом " . $elemHL["UF_CODE"] . " прошло успешно\n";

    $elemHL["isOverwritten"] = true;
}
echo "Закончили добавление новых элементов\n\n";


echo "ВСЕ ГОТОВО\n\n";
echo FINAL_MESSAGE;