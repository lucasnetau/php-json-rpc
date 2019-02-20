<?php declare(strict_types=1);

namespace EdgeTelemetrics\JSON_RPC;

class Request extends Notification implements \JsonSerializable {

    /**
     * @var string|int|null
     */
    protected $id;

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function jsonSerialize()
    {
        $record = parent::jsonSerialize();
        $record['id'] = $this->id;
        return $record;
    }
}