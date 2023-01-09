<?
namespace Handlers;

use \Bitrix\Main\Loader,
    \Bitrix\Tasks\Scrum\Internal\ItemTable,
    \Bitrix\Tasks\TaskTable;

class TasksHandlers
{
	public function ScramPointsAdd($id, $fields)
	{
        $pointsField = \IteesFunctions::getUserTypeEntityCodeByXml('STORY_POINTS', 'TASKS_TASK');
        if (isset($fields[$pointsField])) {
            Loader::includeModule('tasks');
            $item = ItemTable::getList(['filter' => ['SOURCE_ID' => $id]])->fetch();
            if ($item['ID'] > 0 && !$item['STORY_POINTS']) {
                ItemTable::update($item['ID'], ['STORY_POINTS' => $fields[$pointsField]]);
            }
        }
	}
    
    public function taskStoryPointsAdd($event) {
        Loader::includeModule('tasks');
        
        $id = $event->getParameter('id')['ID'];
        $points = $event->getParameter('fields')['STORY_POINTS'];
        if (!$points) return;
        
        $taskId = ItemTable::getList(['filter' => ['ID' => $id]])->fetch()['SOURCE_ID'];
        $pointsField = \IteesFunctions::getUserTypeEntityCodeByXml('STORY_POINTS', 'TASKS_TASK');
        
        $task = TaskTable::getList([
            'filter' => ['ID' => $taskId],
            'select' => ['ID', $pointsField]
        ])->fetch();
        
        if (empty($task[$pointsField])) {
            TaskTable::update(
                $taskId,
                [$pointsField => $points]
            );
        }
    }
}