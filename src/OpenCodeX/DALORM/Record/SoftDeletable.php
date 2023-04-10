<?php

namespace OpenCodeX\DALORM\Record;

trait SoftDeletable
{
    public function delete(): static
    {
        return $this->update([
            'deleted_at' => new \DateTime(),
        ]);
    }

    /*public function forceDelete(): static
    {
        return parent::delete();
    }*/
}
