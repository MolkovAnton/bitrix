<?
namespace Handlers;

class RestHandlers
{
    public function setRestMethods() {
        return [
            'user' => [
                'MA.getUserHistory' => [
                    'callback' => '\Functions\UserFunctions::getUserHistory',
                    'options' => []
                ],
                'MA.user.add' => [
                    'callback' => '\Functions\UserFunctions::userAdd',
                    'options' => []
                ]
            ],
            'department' => [
                'MA.getDepartmentsTree' => [
                    'callback' => '\Functions\CompanyStructure::getDepartmentsTree',
                    'options' => []
                ],
                'MA.getDepartmentsHistory' => [
                    'callback' => '\Functions\IblockFunctions::getDepartmentsHistory',
                    'options' => []
                ],
                'MA.exportUsersCsv' => [
                    'callback' => '\Functions\CompanyStructure::exportUsersCsv',
                    'options' => []
                ]
            ],
            'rpa' => [
                'MA.jira.send' => [
                    'callback' => '\Jira\Connector::sendFormRest',
                    'options' => []
                ],
            ]
        ];
    }
}