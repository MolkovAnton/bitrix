<?php
namespace MA\Eventhandlers\Controller;

use \Bitrix\Main;

class Export extends Main\Controller\Export
{
	protected $module = 'ma.eventhandlers';

    public function init() {
        $this->keepFieldInProcess('url');
        $this->keepFieldInProcess('pageSize');

		$this->url = $this->request->get('URL');
        $this->pageSize = $this->request->get('PAGE_SIZE');
        $step = $this->getProgressParameters()['stepCount'];

        $page = $step ? $step + 1 : 1;
        $this->componentParameters['URL'] = $this->url.$page;
        $this->componentParameters['FIRST_PAGE'] = !$step;
        
        parent::init();
    }
    
    public function exportAction()
	{
		/** @global \CMain */
		global $APPLICATION;

		if ($this->isNewProcess)
		{
			$this->fileType = $this->getFileType();
			$this->fileName = $this->generateExportFileName();
			$this->filePath = $this->generateTempDirPath(). $this->fileName;
			$this->processedItems = 0;
			$this->totalItems = 0;
			$this->saveProgressParameters();
		}

        $nextPage = (int)floor($this->processedItems / $this->pageSize) + 1;

        $componentParameters = array_merge(
            $this->componentParameters,
            array(
                'STEXPORT_MODE' => 'Y',
                'EXPORT_TYPE' => $this->exportType,
                'STEXPORT_PAGE_SIZE' => $this->pageSize,
                'STEXPORT_TOTAL_ITEMS' => $this->totalItems,
                'STEXPORT_LAST_EXPORTED_ID' => $this->lastExportedId,
                'PAGE_NUMBER' => $nextPage,
            )
        );

        ob_start();
        $componentResult = $APPLICATION->IncludeComponent(
            $this->componentName,
            '',
            $componentParameters
        );
        $exportData = ob_get_contents();
        ob_end_clean();

        $processedItemsOnStep = 0;

        if (is_array($componentResult))
        {
            if (isset($componentResult['ERROR']))
            {
                $this->addError(new Error($componentResult['ERROR']));

            }
            else
            {
                if (isset($componentResult['PROCESSED_ITEMS']))
                {
                    $processedItemsOnStep = (int)$componentResult['PROCESSED_ITEMS'];
                }

                // Get total items quantity on 1st step.
                if ($nextPage === 1 && isset($componentResult['TOTAL_ITEMS']))
                {
                    $this->totalItems = (int)$componentResult['TOTAL_ITEMS'];
                }

                if (isset($componentResult['LAST_EXPORTED_ID']))
                {
                    $this->lastExportedId = (int)$componentResult['LAST_EXPORTED_ID'];
                }
            }
        }

        if ($processedItemsOnStep > 0)
        {
            $this->processedItems += $processedItemsOnStep;

            $this->writeTempFile($exportData, ($nextPage === 1));
            unset($exportData);

            $this->isExportCompleted = ($this->processedItems >= $this->totalItems);

            if ($this->isExportCompleted && !$this->isCloudAvailable)
            {
                // adjust completed file size
                $this->fileSize = $this->getSizeTempFile();
            }
        }
        elseif ($processedItemsOnStep == 0)
        {
            // Smth went wrong - terminate process.
            $this->isExportCompleted = true;

            if (!$this->isCloudAvailable)
            {
                $this->fileSize = $this->getSizeTempFile();
            }
        }


		if ($this->totalItems == 0)
		{
			$this->isExportCompleted = true;

			// Save progress
			$this->saveProgressParameters();

			// finish
			$result = $this->preformAnswer(self::ACTION_VOID);
			$result['STATUS'] = self::STATUS_COMPLETED;
		}
		else
		{
			// Save progress
			$this->saveProgressParameters();

			$result = $this->preformAnswer(self::ACTION_EXPORT);
			$result['STATUS'] = self::STATUS_PROGRESS;
		}

		return $result;
	}
}