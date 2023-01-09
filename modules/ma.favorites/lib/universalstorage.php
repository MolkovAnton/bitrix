<?php
namespace MA\Favorites;

class UniversalStorage extends LocalStorage
{
    /** @var DatabaseStorage */
    private $databaseStorage = null;
    
    /**
     * UniversalStorage constructor.
     * @param $type
     */
	public function __construct($type)
    {
        parent::__construct($type);
        /** @global \CUser $USER */
        global $USER;
        $userId = null;
        if (!empty($USER) && ($USER instanceof \CUser) && $USER->IsAuthorized())
        {
            $userId = (int)$USER->GetID();
        }

        $this->databaseStorage = $userId > 0 ? new DatabaseStorage($userId, $type) : null;
        
        if ($this->databaseStorage instanceof DatabaseStorage && !$this->request->getCookie($this->_getCookieName())) {
            $items = $this->databaseStorage->getUserElements();
            foreach ($items as $id) {
                $this->add($id);
            }
        }
    }
    
    /**
     * @param int $id
     */
    public function add($id) {
        parent::add($id);
        if ($this->databaseStorage instanceof DatabaseStorage) {
            $this->databaseStorage->add($id);
        } else {
            $this->unMarkDelete($id);
        }
    }
    
    /**
     * @param int $id
     */
    public function delete($id) {
        parent::delete($id);
        if ($this->databaseStorage instanceof DatabaseStorage) {
            $this->databaseStorage->delete($id);
        } else {
            $this->markDelete($id);
        }
    }
    
    /**
     * @param int $id
     */
    public function handle($id) {
        if ($this->has($id)) {
            $this->delete($id);
        } else {
            $this->add($id);
        }
    }
}