<?php

namespace Oro\Bundle\ZendeskBundle\Tests\Functional\ImportExport\Job;

use Oro\Bundle\ZendeskBundle\Provider\TicketCommentConnector;
use Oro\Bundle\ZendeskBundle\Provider\Transport\Rest\Exception\InvalidRecordException;
use Oro\Bundle\ZendeskBundle\Tests\Functional\DataFixtures\LoadTicketData;

/** @dbIsolationPerTest */
class TicketCommentExportJobTest extends AbstractImportExportJobTestCase
{
    /** {@inheritdoc} */
    protected function setUp()
    {
        parent::setUp();
        $this->loadFixtures([LoadTicketData::class]);
    }

    public function testExportTicketCommentForCloseTicket()
    {
        $channel        = $this->getReference('zendesk_channel:first_test_channel');
        $ticketComment  = $this->getReference('zendesk_ticket_42_comment_3');

        $exception = new InvalidRecordException('', 422);

        $this->resource->expects($this->once())
            ->method('addTicketComment')
            ->with($ticketComment)
            ->willThrowException($exception);

        $jobLog = [];

        $result = $this->runImportExportConnectorsJob(
            self::SYNC_PROCESSOR,
            $channel,
            TicketCommentConnector::TYPE,
            [
                'id' => $ticketComment->getId()
            ],
            $jobLog
        );

        $log = $this->formatImportExportJobLog($jobLog);

        $expectedMessage = "Some entities were skipped due to warnings: ";
        $expectedMessage .= "Error ticket comment not exported because ticket is closed";

        $this->assertContains($expectedMessage, $log);

        $this->assertTrue($result, "Job Failed with output:\n $log");
    }
}
