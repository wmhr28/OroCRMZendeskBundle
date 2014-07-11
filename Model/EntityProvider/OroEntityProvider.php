<?php

namespace OroCRM\Bundle\ZendeskBundle\Model\EntityProvider;

use Doctrine\ORM\EntityManager;

use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\UserBundle\Entity\Email;
use Oro\Bundle\UserBundle\Entity\User as OroUser;
use OroCRM\Bundle\CaseBundle\Entity\CaseEntity;
use OroCRM\Bundle\ContactBundle\Entity\ContactEmail;
use OroCRM\Bundle\ZendeskBundle\Entity\User as ZendeskUser;
use OroCRM\Bundle\ContactBundle\Entity\Contact;
use OroCRM\Bundle\ContactBundle\Entity\ContactPhone;
use OroCRM\Bundle\ZendeskBundle\Provider\ChannelType;

class OroEntityProvider
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param Channel $channel
     * @return null|OroUser
     */
    public function getDefaultUser(Channel $channel)
    {
        $user = $channel->getDefaultUserOwner();
        if ($user) {
            $user = $this->entityManager->getRepository('OroUserBundle:User')
                ->find($user->getId());
        }
        return $user;
    }

    /**
     * @param ZendeskUser $user
     * @param bool        $defaultIfNotExist
     * @return OroUser|null
     */
    public function getUser(ZendeskUser $user, $defaultIfNotExist = false)
    {
        $oroUser = $this->entityManager->getRepository('OroUserBundle:User')
            ->findOneBy(array('email' => $user->getEmail()));

        if (!$oroUser) {
            /**
             * @var Email $email
             */
            $email = $this->entityManager->getRepository('OroUserBundle:Email')
                ->findOneBy(
                    array(
                        'email' => $user->getEmail()
                    )
                );

            if ($email) {
                $oroUser = $email->getUser();
            }
        }

        if ($defaultIfNotExist && !$oroUser) {
            $oroUser = $this->getDefaultUser($user->getChannel());
        }

        return $oroUser;
    }

    /**
     * @param ZendeskUser $user
     * @return Contact|null
     */
    public function getContact(ZendeskUser $user)
    {
        if (!$user->getEmail()) {
            return null;
        }
        /**
         * @var ContactEmail $contactEmail
         */
        $contactEmail = $this->entityManager->getRepository('OroCRMContactBundle:ContactEmail')
            ->findOneBy(
                array(
                    'email' => $user->getEmail()
                ),
                array('primary' => 'DESC')
            );

        if ($contactEmail) {
            return $contactEmail->getOwner();
        }

        $contact = new Contact();

        if ($user->getPhone()) {
            $phone = new ContactPhone();
            $phone->setPrimary(true);
            $phone->setPhone($user->getPhone());
            $contact->addPhone($phone);
        }

        $email = new ContactEmail();
        $email->setPrimary(true);
        $email->setEmail($user->getEmail());
        $contact->addEmail($email);

        $nameParts = preg_split('/[\s]+/', trim($user->getName()), 2);
        $nameParts = array_pad($nameParts, 2, '');
        $contact->setFirstName($nameParts[0]);
        $contact->setLastName($nameParts[1]);

        return $contact;
    }

    /**
     * @param $channelId
     * @return null|Channel
     */
    public function getChannelById($channelId)
    {
        return $this->entityManager->getRepository('OroIntegrationBundle:Channel')->find($channelId);
    }

    /**
     * Get all enabled Zendesk channels
     *
     * @return Channel[]
     */
    public function getEnabledChannels()
    {
        return $this->entityManager->getRepository('OroIntegrationBundle:Channel')
            ->findBy(array('type' => ChannelType::TYPE, 'enabled' => true));
    }

    /**
     * Get all enabled Zendesk channels with enabled two way sync
     *
     * @return Channel[]
     */
    public function getEnabledTwoWaySyncChannels()
    {
        return array_filter(
            $this->getEnabledChannels(),
            function (Channel $channel) {
                return $channel->getSynchronizationSettings()->offsetGetOr('isTwoWaySyncEnabled', false);
            }
        );
    }

    /**
     * @param $id
     *
     * @return null|CaseEntity
     */
    public function getCaseById($id)
    {
        return $this->entityManager->getRepository('OroCRMCaseBundle:CaseEntity')
            ->find($id);
    }
}
