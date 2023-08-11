<?php

use GenericParameterJob;
use Job;

class DisplayTitlePurgeIncomingLinksJob extends Job implements GenericParameterJob
{

    function __construct(array $params)
    {
        parent::__construct('DisplayTitlePurgeIncomingLinksJob', $params);
    }

    /**
     * Run the job
     * @return bool success
     */
    public function run()
    {
        $this->params['page']->doPurge();
        return true;
    }
}