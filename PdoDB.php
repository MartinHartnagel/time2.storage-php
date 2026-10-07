<?php

abstract class PdoDB
{
    public $prefix;
    public $db;

    public function loadLayoutAndChanged($at, &$layout, &$changed)
    {
        assert($at != null, 'layout to load at is null');
        $stmt = $this->db->prepare('SELECT `value`, `time` FROM `' . $this->prefix . 'LAYOUT` WHERE `time` <= ? ORDER BY `time` DESC LIMIT 1');
        $stmt->execute([$at]);
        $data = $stmt->fetch();
        if (!$data) {
            $layout = null;
            $changed = null;
            return;
        }
        $layout = $data['value'];
        $changed = $data['time'];
    }

    public function storeLayout($at, $value)
    {
        assert($at != null, 'layout to store at is null');
        assert($value != null, 'layout to store is null');
        $sql = 'DELETE FROM `' . $this->prefix . 'LAYOUT` WHERE `time` = :time';
        $statement = $this->db->prepare($sql);
        $statement->execute(['time' => $at]);

        $sql = 'INSERT INTO `' . $this->prefix . 'LAYOUT` (`time`, `value`) VALUES (?, ?)';
        $statement = $this->db->prepare($sql);
        $statement->execute([$at, $value]);
    }

    public function loadEventsOnDay($day, &$events)
    {
        assert($day != null, 'day to load events for is null');
        assert(strlen($day) != 10, 'day to load events for has invalid length: "' . $day . '"');
        $es = [];

        // Events:

        $stmt = $this->db->prepare('SELECT `time`, `name`, `color`, `end` FROM `' . $this->prefix . 'EVENT` WHERE `time` >= ? AND `time` <= ? ORDER BY `time` DESC');
        $stmt->execute([strtotime($day) * 1000, strtotime($day . 'T23:59:59') * 1000]);

        $lastTime = '';
        while ($data = $stmt->fetch()) {
            if (strlen($data['name']) > 0) {
                $e = $data['time'] . "\t" . $data['name'] . "\t" . $data['color'];
                if ($data['end'] != null) {
                    $e .= "\t" . $data['end'];
                } else if (strlen($lastTime) > 0) {
                    $e .= "\t" . $lastTime;
                }
                $es[$data['time'] . 'e'] = $e;
                $lastTime = $data['time'];
            }
        }

        // Infos:

        $stmt = $this->db->prepare('SELECT `time`, `info` FROM `' . $this->prefix . 'INFO` WHERE `time` >= ? AND `time` <= ? ORDER BY `time` ASC');
        $stmt->execute([strtotime($day) * 1000, strtotime($day . 'T23:59:59') * 1000]);

        while ($data = $stmt->fetch()) {
            if (strlen($data['info']) > 0) {
                $es[$data['time'] . 'i'] = $data['time'] . "\t" . $data['info'];
            }
        }

        ksort($es);
        $c = '';
        foreach ($es as $t => $e) {
            if (strlen($c) > 0) {
                $c .= "\n";
            }
            $c .= $e;
        }
        $events = trim($c);
    }

    public function loadEventsInRange($from, $to, &$events, &$infos)
    {
        assert($from != null, 'events to load from is null');
        assert($to != null, 'events to load to is null');
        $events = [];
        $stmt = $this->db->prepare('SELECT `time`, `name`, `color`, `end` FROM `' . $this->prefix . 'EVENT` WHERE (`end` IS NULL OR `end` >= :from) AND `time` <= :to ORDER BY `time` ASC');
        $stmt->execute(['from' => $from, 'to' => $to]);

        while ($data = $stmt->fetch()) {
            if (strlen($data['name']) > 0) {
                $e = ["s" => $data['time'], "n" => $data['name'], "c" => $data['color']];
                if ($data['end'] != null) {
                    $e["e"] = $data['end'];
                }
                $events[] = $e;
            }
        }

        $infos = [];
        $stmt = $this->db->prepare('SELECT `time`, `info` FROM `' . $this->prefix . 'INFO` WHERE `time` >= ? AND `time` <= ? ORDER BY `time` ASC');
        $stmt->execute([$from, $to]);

        while ($data = $stmt->fetch()) {
            if (strlen($data['info']) > 0) {
                $infos[] = ["s" => $data['time'], "i" => $data['info']];
            }
        }
    }

    public function storeEvent($time, $name, $color, $end)
    {
        assert($time != null, 'event to store time is null');
        assert($name != null, 'event to store name is null');
        assert(strlen($name) == 0, 'event to store name length is 0');
        assert($color != null, 'event to store color is null');
        assert(strlen($color) == 0, 'event to store color length is 0');
        $sql = 'DELETE FROM `' . $this->prefix . 'EVENT` WHERE `time` = :time';
        $statement = $this->db->prepare($sql);
        $statement->execute(['time' => $time]);

        $sql = 'UPDATE `' . $this->prefix . 'EVENT` SET `end` = :time WHERE `time` < :time and (`end` is null OR `end` > :time)';
        $statement = $this->db->prepare($sql);
        $statement->execute(['time' => $time]);

        if ($end != $time) {
            $sql = 'INSERT INTO `' . $this->prefix . 'EVENT` (`time`, `name`, `color`, `end`) VALUES (:time, :name, :color, :end)';
            $statement = $this->db->prepare($sql);
            $statement->execute(['time' => $time, 'name' => $name, 'color' => $color, 'end' => $end]);
        }
    }

    public function storeInfo($time, $info)
    {
        assert($time != null, 'info to store time is null');
        assert($info != null, 'info to store is null');
        $sql = 'DELETE FROM `' . $this->prefix . 'INFO` WHERE `time` = :time';
        $statement = $this->db->prepare($sql);
        $statement->execute(['time' => $time]);

        $sql = 'INSERT INTO `' . $this->prefix . 'INFO` (`time`, `info`) VALUES (:time, :info)';
        $statement = $this->db->prepare($sql);
        $statement->execute(['time' => $time, 'info' => $info]);
    }

    public function loadInvoiceValue($key)
    {
        assert($key != null, 'invoice keyValue key to load is null');
        $stmt = $this->db->prepare('SELECT `value` FROM `' . $this->prefix . 'INVOICE` WHERE `key` = :key');
        $stmt->execute(['key' => $key]);
        $data = $stmt->fetch();
        if (!$data) {
            return null;
        }
        return $data['value'];
    }

    public function storeInvoice($invoiceNumber, $extracted)
    {
        assert($invoiceNumber != null, 'invoice invoiceNumber to store is null');
        $this->storeInvoiceKeyValue('invoice_' . $invoiceNumber, json_encode($extracted['invoice']));
        if (isset($extracted['twigs'])) {
            foreach ($extracted['twigs'] as $k => $v) {
                $this->storeInvoiceKeyValue($k, $v);
            }
        }
        if (isset($extracted['assets'])) {
            foreach ($extracted['assets'] as $k => $v) {
                $this->storeInvoiceKeyValue($k, $v);
            }
        }
    }

    private function storeInvoiceKeyValue($key, $value)
    {
        assert($key != null, 'invoice keyValue key to store is null');
        $this->deleteInvoiceKeyValue($key);
        $sql = 'INSERT INTO `' . $this->prefix . 'INVOICE` (`key`, `value`) VALUES (:key, :value)';
        $statement = $this->db->prepare($sql);
        $statement->execute(['key' => $key, 'value' => $value]);
    }

    public function loadNote($id)
    {
        assert($id != null, 'note id to load is null');
        $key = 'note_' . $id;
        $stmt = $this->db->prepare('SELECT `value` FROM `' . $this->prefix . 'NOTE` WHERE `key` = :key');
        $stmt->execute(['key' => $key]);
        $data = $stmt->fetch();
        if (!$data) {
            return null;
        }
        return $data['value'];
    }

    public function storeNote($id, $obj)
    {
        assert($id != null, 'note id is null');
        $this->storeNoteKeyValue('note_' . $id, json_encode($obj));
    }

    private function storeNoteKeyValue($key, $value)
    {
        assert($key != null, 'note keyValue key is null');
        $this->deleteNoteKeyValue($key);
        $sql = 'INSERT INTO `' . $this->prefix . 'NOTE` (`key`, `value`) VALUES (:key, :value)';
        $statement = $this->db->prepare($sql);
        $statement->execute(['key' => $key, 'value' => $value]);
    }

    public function deleteInvoice($invoiceNumber)
    {
        assert($invoiceNumber != null, 'invoice invoiceNumber is null');
        $key = 'invoice_' . $invoiceNumber;
        $this->deleteInvoiceKeyValue($key);
    }

    private function deleteInvoiceKeyValue($key)
    {
        assert($key != null, 'invoice keyValue key to delete is null');
        $sql = 'DELETE FROM `' . $this->prefix . 'INVOICE` WHERE `key` = :key';
        $statement = $this->db->prepare($sql);
        $statement->execute(['key' => $key]);
    }

    public function deleteNote($id)
    {
        assert($id != null, 'note id to delete is null');
        $key = 'note_' . $id;
        $this->deleteNoteKeyValue($key);
    }

    private function deleteNoteKeyValue($key)
    {
        assert($key != null, 'note keyValue key to delete is null');
        $sql = 'DELETE FROM `' . $this->prefix . 'NOTE` WHERE `key` = :key';
        $statement = $this->db->prepare($sql);
        $statement->execute(['key' => $key]);
    }

    public function deleteAllInvoices()
    {
        $sql = 'DELETE FROM `' . $this->prefix . 'INVOICE`';
        $statement = $this->db->prepare($sql);
        $statement->execute([]);
    }

    public function deleteAllNotes()
    {
        $sql = 'DELETE FROM `' . $this->prefix . 'NOTE`';
        $statement = $this->db->prepare($sql);
        $statement->execute([]);
    }

    public function cleanup()
    {
        // orphaned twigs and assets
        $stmt = $this->db->prepare('SELECT `value` FROM `' . $this->prefix . 'INVOICE` WHERE `key` like \'invoice_%\'');
        $stmt->execute([]);
        $inUse = [];
        while ($data = $stmt->fetch()) {
            $invoice = json_decode($data['value'], true);
            $inUse[$invoice['main']] = true;
            $inUse[$invoice['footer']] = true;
            foreach ($invoice['assets'] as $key) {
                $inUse[$key] = true;
            }
        }
        $stmt = $this->db->prepare('SELECT `key` FROM `' . $this->prefix . 'INVOICE` WHERE `key` not like \'invoice_%\'');
        $stmt->execute([]);
        $orphaned = [];
        while ($data = $stmt->fetch()) {
            if (!array_key_exists($data['key'], $inUse)) {
                $orphaned[] = $data['key'];
            }
        }
        foreach ($orphaned as $key) {
            $this->deleteInvoiceKeyValue($key);
        }
    }
}
