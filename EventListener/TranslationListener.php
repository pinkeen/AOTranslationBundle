<?php
namespace AO\TranslationBundle\EventListener;

use AO\TranslationBundle\Entity;
use AO\TranslationBundle\Translation\Message;
use AO\TranslationBundle\Translation\Translator;
use Symfony\Component\EventDispatcher\Event;
use AO\TranslationBundle\Translation;
use Doctrine\ORM\EntityManager;

/**
 * @author Adrian Olek <adrianolek@gmail.com>
 *
 * Stores translation messages info.
 */
class TranslationListener
{
    /**
     * @var EntityManager
     */
    private $em;

    public function __construct(Translator $translator, EntityManager $entityManager)
    {
        $this->translator = $translator;
        $this->em = $entityManager;
    }

    public function onCommand(Event $event)
    {
        $command = $event->getCommand();
        $class = get_class($command);
        preg_match('/^(.+Bundle)\\\\.+\\\\(.+)$/', $class, $matches);
        $bundle = str_replace('\\', '', $matches[1]);

        $this->translator->setCommand($bundle, $matches[2], $command->getName());
    }

    /**
     * Returns Cache entity for given message.
     *
     * Creates it if not exists.
     *
     * @param Message $message
     * @return Entity\Cache
     */
    protected function getCacheForMessage(Message $message)
    {
        $cache = $this->em
            ->getRepository('AO\TranslationBundle\Entity\Cache')
            ->findOneBy(array(
                'bundle' => $message->getBundle(),
                'controller' => $message->getController(),
                'action' => $message->getAction()
            ))
        ;

        if (!$cache) {
            $cache = new Entity\Cache();
            $cache->setBundle($message->getBundle());
            $cache->setController($message->getController());
            $cache->setAction($message->getAction());

            $this->em->persist($cache);
            $this->em->flush();            
        }

        return $cache;
    }

    /**
     * Returns Domain entity by name.
     *
     * Creates it if not exists.
     *
     * @param string $name
     * @return Entity\Domain
     */
    protected function getDomain($name) 
    {
        $domain = $this->em
            ->getRepository('AO\TranslationBundle\Entity\Domain')
            ->findOneByName($name)
        ;

        if (!$domain) {
            $domain = new Domain();
            $domain->setName($name);

            $this->em->persist($domain);
            $this->em->flush();
        }

        return $domain;
    }

    /**
     * Save domain, message & cache info on kernel.terminate
     * @param Event $event
     */
    public function onTerminate(Event $event)
    {
        $messages = $this->translator->getMessages();

        // prepare domains ids array for new messages
        $domains = array();
        // prepare cache ids array for not cached messages
        $caches = array();

        foreach ($messages as $domain => $domainMessages) {
            foreach ($domainMessages as $message) {
                if ($message->isNew() && !array_key_exists($domain, $domains)) {
                    // we need domain ids only for new messages
                    $domains[$domain] = $this->getDomain($domain);
                }

                if (!$message->isCached() && !array_key_exists($message->getCacheKey(), $caches)) {
                    // we need cache ids only for not cached messages
                    $caches[$message->getCacheKey()] = $this->getCacheForMessage($message);
                }
            }
        }

        // save messages
        foreach ($messages as $domain => $domainMessages) {
            foreach ($domainMessages as $message) {
                // create new message
                if ($message->isNew()) {
                    $messageEntity = new Entity\Message();
                    $messageEntity->setIdentification($message->getIdentification());
                    $messageEntity->setDomain($domains[$message->getDomain()]);
                    $messageEntity->setParameters($message->getParameters());

                    $message->setEntity($messageEntity);

                    $this->em->persist($messageEntity);
                    $this->em->flush();
                }

                // add cache
                if (!$message->isCached()) {
                    $messageEntity = $message->getEntity();

                    $messageCache = $caches[$message->getCacheKey()];
                    $messageCaches = $messageEntity->getCaches();

                    if (!$messageCaches->contains($messageCache)) {
                        $messageCaches->add($messageCache);
                    }

                    $this->em->persist($messageEntity);
                }

                // update parameters if needed
                if ($message->getUpdateParameters()) {
                    $messageEntity = $message->getEntity();
                    $messageEntity->setParameters($message->getParameters());

                    $this->em->persist($messageEntity);
                }
            }
        }

        $this->em->flush();
    }
}
