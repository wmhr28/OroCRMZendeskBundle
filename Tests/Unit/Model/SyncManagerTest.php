<?php

namespace OroCRM\Bundle\ZendeskBundle\Tests\Unit\Model;

use OroCRM\Bundle\ZendeskBundle\Entity\Ticket;
use OroCRM\Bundle\ZendeskBundle\Entity\TicketComment;
use OroCRM\Bundle\ZendeskBundle\Model\SyncManager;
use OroCRM\Bundle\ZendeskBundle\Provider\TicketCommentConnector;

class SyncManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var SyncManager
     */
    protected $target;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $scheduler;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $entityManager;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $zendeskEntityProvider;

    protected function setUp()
    {
        $this->scheduler = $this->getMockBuilder('Oro\Bundle\IntegrationBundle\Manager\SyncScheduler')
            ->disableOriginalConstructor()
            ->getMock();
        $this->entityManager = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $this->zendeskEntityProvider = $this->getMockBuilder(
            'OroCRM\Bundle\ZendeskBundle\Model\EntityProvider\ZendeskEntityProvider'
        )
            ->disableOriginalConstructor()
            ->getMock();

        $this->target = new SyncManager($this->scheduler, $this->entityManager, $this->zendeskEntityProvider);
    }

    public function testSyncCommentSyncOnlyNewComments()
    {
        $existComment = $this->getMock('OroCRM\Bundle\CaseBundle\Entity\CaseComment');
        $existComment->expects($this->once())
            ->method('getId')
            ->will($this->returnValue(42));
        $this->zendeskEntityProvider->expects($this->never())
            ->method('getTicketByCase');
        $this->target->syncComment($existComment);
    }

    public function testSyncCommentSync()
    {
        $comment = $this->getMock('OroCRM\Bundle\CaseBundle\Entity\CaseComment');
        $case = $this->getMock('OroCRM\Bundle\CaseBundle\Entity\CaseEntity');
        $comment->expects($this->once())
            ->method('getId')
            ->will($this->returnValue(null));
        $comment->expects($this->once())
            ->method('getCase')
            ->will($this->returnValue($case));
        $ticket = $this->getMock('OroCRM\Bundle\ZendeskBundle\Entity\Ticket');
        $channel = $this->getMock('Oro\Bundle\IntegrationBundle\Entity\Channel');
        $ticket->expects($this->once())
            ->method('getChannel')
            ->will($this->returnValue($channel));

        $this->zendeskEntityProvider->expects($this->once())
            ->method('getTicketByCase')
            ->will($this->returnValue($ticket));
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with(
                $this->callback(
                    function (TicketComment $ticketComment) use ($channel, $ticket, $comment) {
                        $this->assertEquals($channel, $ticketComment->getChannel());
                        $this->assertEquals($ticket, $ticketComment->getTicket());
                        $this->assertEquals($comment, $ticketComment->getRelatedComment());
                        return true;
                    }
                )
            );
        $this->scheduler->expects($this->once())
            ->method('schedule')
            ->with($channel, TicketCommentConnector::TYPE, $this->arrayHasKey('id'), false);
        $this->target->syncComment($comment);
    }

    public function testSyncCase()
    {
        $case = $this->getMock('OroCRM\Bundle\CaseBundle\Entity\CaseEntity');
        $firstComment = $this->getMock('OroCRM\Bundle\CaseBundle\Entity\CaseComment');
        $secondComment = $this->getMock('OroCRM\Bundle\CaseBundle\Entity\CaseComment');
        $comments = array(
            $firstComment,
            $secondComment
        );
        $case->expects($this->once())
            ->method('getComments')
            ->will($this->returnValue($comments));
        $channel = $this->getMock('Oro\Bundle\IntegrationBundle\Entity\Channel');
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with(
                $this->callback(
                    function (Ticket $ticket) use ($channel, $comments, $case) {
                        $this->assertEquals($channel, $ticket->getChannel());
                        $this->assertEquals($case, $ticket->getRelatedCase());
                        $this->assertCount(count($comments), $ticket->getComments());
                        foreach ($ticket->getComments() as $ticketComment) {
                            $this->assertEquals(current($comments), $ticketComment->getRelatedComment());
                            $this->assertEquals($channel, $ticketComment->getChannel());
                            next($comments);
                        }
                        return true;
                    }
                )
            );
        $this->target->syncCase($case, $channel);
    }
}