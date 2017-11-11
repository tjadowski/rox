<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Member;
use AppBundle\Entity\Message;
use AppBundle\Form\MessageRequestType;
use AppBundle\Form\MessageToMemberType;
use AppBundle\Model\MessageModel;
use AppBundle\Model\RequestModel;
use Html2Text\Html2Text;
use InvalidArgumentException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Class RequestAndMessageController.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RequestAndMessageController extends Controller
{
    /*    public function update(Request $request)
        {
            $modifyAction = $request->request->get('modify');
            $messageIds = $request->request->get('message_id');

            $member = $this->getUser();

            $message = new Message();

            $messages = $message->newQuery()->findMany($messageIds);

            foreach ($messages as $message) {
                if ($modifyAction === 'delete') {
                    $this->messageService->deleteMessage($message, $member);
                } elseif ($modifyAction === 'markasspam') {
                    $this->messageService->moveMessage($message, Message::FOLDER_SPAM);
                } elseif ($modifyAction === 'nospam') {
                    $this->messageService->moveMessage($message, Message::FOLDER_INBOX);
                //} else {
                    //throw new \InvalidArgumentException('Invalid message state.');
                }
            }

            return new RedirectResponse($request->getUri());
        }

        public function with(Request $request)
        {
            $page = $request->query->get('page', 1);
            $limit = $request->query->get('limit', 10);
            //$sort = $request->query->get('sort', 'date');
            //$dir = $request->query->get('dir', 'DESC');
            $otherUsername = $request->attributes->get('username');

            $otherMember = $this->memberRepository->getByUsername($otherUsername);

            $member = $this->getUser();

            $message = new Message();

            $q = $message->newQuery();

            // Eager load each sender for each message
            $q->with('sender');

            $q->where(function (Builder $builder) use ($member, $otherMember) {
                $builder->where(function (Builder $builder) use ($member, $otherMember) {
                    $builder->where('IdSender', $otherMember->id);
                    $builder->where('IdReceiver', $member->id);
                    $builder->where('Status', 'Sent');
                });

                $builder->orWhere(function (Builder $builder) use ($member, $otherMember) {
                    $builder->where('IdSender', $member->id);
                    $builder->where('IdReceiver', $otherMember->id);
                });
            });

            $q->where('DeleteRequest', 'NOT LIKE', '%receiverdeleted%');

            $q->orderByRaw('IF(messages.created > messages.DateSent, messages.created, messages.DateSent) DESC');

            $q->forPage($page, $limit);

            $count = $q->getQuery()->getCountForPagination();

            $messages = $q->get();

            $content = $this->render('@message/message/index.html.twig', [
                'messages' => $messages,
                'folder' => '',
                'filter' => $request->query->all(),
                'page' => $page,
                'pages' => ceil($count / $limit),
            ]);

            return new Response($content);
        }
    */

    /**
     * @Route("/message/{id}/reply", name="message_reply",
     *     requirements={"id": "\d+"})
     *
     * @param Request $request
     * @param Message $message
     *
     * @throws \Doctrine\DBAL\DBALException
     *
     * @return Response
     */
    public function reply(Request $request, Message $message)
    {
        $sender = $this->getUser();
        if (($message->getReceiver() !== $sender) && ($message->getSender() !== $sender)) {
            throw new AccessDeniedException();
        }

        if (null !== $message->getRequest()) {
            return $this->redirectToRoute('hosting_request_reply', ['id' => $message->getId()]);
        }

        $messageModel = new MessageModel($this->getDoctrine());
        $thread = $messageModel->getThreadForMessage($message);

        $replyMessage = new Message();
        $replyMessage->setSubject($message->getSubject());

        $messageForm = $this->createForm(MessageToMemberType::class, $replyMessage);
        $messageForm->handleRequest($request);

        if ($messageForm->isSubmitted() && $messageForm->isValid()) {
            $receiver = ($message->getReceiver() === $sender) ? $message->getSender() : $message->getReceiver();
            $replyMessage = $messageForm->getData();
            $replyMessage->setParent($message);
            $replyMessage->setSender($sender);
            $replyMessage->setReceiver($receiver);
            $replyMessage->setInfolder('normal');
            $replyMessage->setCreated(new \DateTime());

            $subject = $message->getSubject();
            $replySubject = $replyMessage->getSubject()->getSubject();
            if (null === $subject || $subject->getSubject() !== $replySubject) {
                $subject = $replyMessage->getSubject();
            }
            $replyMessage->setSubject($subject);
            $em = $this->getDoctrine()->getManager();
            $em->persist($replyMessage);
            $em->flush();

            // $replyMessage->refresh();
            return $this->redirectToRoute('message_show', ['id' => $replyMessage->getId()]);
        }

        return $this->render(':message:reply.html.twig', [
            'form' => $messageForm->createView(),
            'current' => $message,
            'thread' => $thread,
        ]);
    }

    /**
     * @Route("/message/{id}", name="message_show",
     *     requirements={"id": "\d+"})
     * @Route("/request/{id}", name="hosting_request_show",
     *     requirements={"id": "\d+"})
     *
     * @param Message $message
     *
     * @throws \Doctrine\DBAL\DBALException
     *
     * @return Response
     */
    public function show(Message $message)
    {
        $member = $this->getUser();
        if (($message->getReceiver() !== $member) && ($message->getSender() !== $member)) {
            throw new AccessDeniedException();
        }

        $messageModel = new MessageModel($this->getDoctrine());
        $thread = $messageModel->getThreadForMessage($message);

        if ($message->isUnread() && $member === $message->getReceiver()) {
            // Only mark as read if it is a message and when the receiver reads the message,
            // not when the message is presented to the Sender with url /messages/{id}/sent
            $message->setWhenFirstRead(new \DateTime());
            $em = $this->getDoctrine()->getManager();
            $em->persist($message);
            $em->flush();
        }

        $view = (null === $message->getRequest()) ? ':message:view.html.twig' : ':request:view.html.twig';

        return $this->render($view, [
            'current' => $message,
            'thread' => $thread,
        ]);
    }

    /**
     * @Route("/new/message/{username}", name="message_new")
     *
     * @param Request $request
     * @param Member  $receiver
     *
     * @return Response
     */
    public function newMessageAction(Request $request, Member $receiver)
    {
        $messageForm = $this->createForm(MessageToMemberType::class);
        $messageForm->handleRequest($request);

        if ($messageForm->isSubmitted() && $messageForm->isValid()) {
            // Write request to database after doing some checks
            /** @var Message $hostingRequest */
            $sender = $this->getUser();
            $hostingRequest = $messageForm->getData();
            $hostingRequest->setSender($sender);
            $hostingRequest->setReceiver($receiver);
            $hostingRequest->setInfolder('normal');
            $hostingRequest->setCreated(new \DateTime());

            $em = $this->getDoctrine()->getManager();
            $em->persist($hostingRequest);
            $em->flush();
            $html2Text = new Html2Text($hostingRequest->getMessage());
            $hostingRequestText = $html2Text->getText();
            $message = \Swift_Message::newInstance()
                ->setSubject($hostingRequest->getSubject()->getSubject())
                ->setFrom([
                    'message@bewelcome.org' => 'bewelcome - '.$sender->getUsername(),
                ])
                ->setTo($receiver->getCryptedField('Email'))
                ->setBody(
                    $this->renderView(
                        // app/Resources/views/Emails/registration.html.twig
                        ':emails:request.html.twig',
                        ['request_text' => $hostingRequest->getMessage()]
                    ),
                    'text/html'
                )
                ->addPart(
                    $this->renderView(
                        ':emails:request.txt.twig',
                        ['request_text' => $hostingRequestText]
                    ),
                    'text/plain'
                )
            ;
            $results = $this->get('mailer')->send($message);
            $this->get('logger')->addInfo('Message send: '.$results);
            $this->addFlash('success', 'Message has been sent.');

            return $this->redirectToRoute('members_profile', ['username' => $receiver->getUsername()]);
        }

        return $this->render(':message:message.html.twig', [
            'receiver' => $receiver,
            'form' => $messageForm->createView(),
        ]);
    }

    /**
     * @Route("/new/request/{username}", name="hosting_request")
     *
     * @param Request $request
     * @param Member  $receiver
     *
     * @return Response
     */
    public function newHostingRequestAction(Request $request, Member $receiver)
    {
        $member = $this->getUser();
        if ($member === $receiver) {
            throw new InvalidArgumentException('You can\'t send a request to yourself.');
        }

        $requestForm = $this->createForm(MessageRequestType::class);
        $requestForm->handleRequest($request);

        if ($requestForm->isSubmitted() && $requestForm->isValid()) {
            // Write request to database after doing some checks
            /** @var Message $hostingRequest */
            $sender = $this->getUser();
            $hostingRequest = $requestForm->getData();
            $hostingRequest->setSender($sender);
            $hostingRequest->setReceiver($receiver);
            $hostingRequest->setInfolder('requests');
            $hostingRequest->setCreated(new \DateTime());

            $em = $this->getDoctrine()->getManager();
            $em->persist($hostingRequest);
            $em->flush();

            // Send mail notification
            $html2Text = new Html2Text($hostingRequest->getMessage());
            $hostingRequestText = $html2Text->getText();
            $message = \Swift_Message::newInstance()
                ->setSubject($hostingRequest->getSubject()->getSubject())
                ->setFrom([
                    'request@bewelcome.org' => 'bewelcome - '.$sender->getUsername(),
                ])
                ->setTo($receiver->getCryptedField('Email'))
                ->setBody(
                    $this->renderView(
                        // app/Resources/views/Emails/registration.html.twig
                        'emails/request.html.twig',
                        ['request_text' => $hostingRequest->getMessage()]
                    ),
                    'text/html'
                )
                ->addPart(
                    $this->renderView(
                        'emails/request.txt.twig',
                        ['request_text' => $hostingRequestText]
                    ),
                    'text/plain'
                )
            ;
            $this->get('mailer')->send($message);
            $this->addFlash('success', 'Request has been sent.');

            return $this->redirectToRoute('members_profile', ['username' => $receiver->getUsername()]);
        }

        return $this->render(':request:request.html.twig', [
            'receiver' => $receiver,
            'form' => $requestForm->createView(),
        ]);
    }

    /**
     * @Route("/request/{id}/reply", name="hosting_request_reply")
     *
     * @param Request $request
     * @param Message $hostingRequest
     *
     * @throws \Doctrine\DBAL\DBALException
     *
     * @return Response
     */
    public function hostingRequestReplyAction(Request $request, Message $hostingRequest)
    {
        if (null === $hostingRequest->getRequest()) {
            return $this->redirectToRoute('message_show', ['id' => $hostingRequest->getId()]);
        }

        $sender = $hostingRequest->getSender();
        $receiver = $hostingRequest->getReceiver();

        $requestForm = $this->createForm(MessageRequestType::class, $hostingRequest);
        $requestForm->handleRequest($request);
        $data = $requestForm->getNormData();
        if ($requestForm->isSubmitted() && $requestForm->isValid()) {
        }

        $messageModel = new MessageModel($this->getDoctrine());
        $thread = $messageModel->getThreadForMessage($hostingRequest);

        return $this->render(':request:reply.html.twig', [
            'form' => $requestForm->createView(),
            'data' => $data,
            'sender' => $sender,
            'receiver' => $receiver,
            'current' => $hostingRequest,
            'thread' => $thread,
        ]);
    }

    /**
     * @Route("/messages/{folder}", name="messages",
     *     requirements={ "folder": "requests|inbox|sent|spam|deleted" },
     *     defaults={"folder": "inbox"})
     *
     * @param Request $request
     * @param string  $folder
     *
     * @return Response
     */
    public function messages(Request $request, $folder)
    {
        $page = $request->query->get('page', 1);
        $limit = $request->query->get('limit', 10);
        $sort = $request->query->get('sort', 'datesent');
        $sortDir = $request->query->get('dir', 'desc');

        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException();
        }

        $member = $this->getUser();

        $messageModel = new MessageModel($this->getDoctrine());
        $messages = $messageModel->getFilteredMessages($member, $folder, $sort, $sortDir, $page, $limit);

        return $this->render(':message:index.html.twig', [
            'items' => $messages,
            'type' => 'UserMessages',
            'folder' => $folder,
            'filter' => $request->query->all(),
            'submenu' => [
                'active' => 'messages_'.$folder,
                'items' => $this->getSubMenuItems(),
            ],
        ]);
    }

    /**
     * @Route("/requests/{folder}", name="requests",
     *     requirements={ "folder": "inbox|sent" },
     *     defaults={"folder": "inbox"})
     *
     * @param Request $request
     * @param string  $folder
     *
     * @return Response
     */
    public function requests(Request $request, $folder)
    {
        $page = $request->query->get('page', 1);
        $limit = $request->query->get('limit', 10);
        $sort = $request->query->get('sort', 'datesent');
        $sortDir = $request->query->get('dir', 'desc');

        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException();
        }

        $member = $this->getUser();

        $requestModel = new RequestModel($this->getDoctrine());
        $requests = $requestModel->getFilteredRequests($member, $folder, $sort, $sortDir, $page, $limit);

        return $this->render(':message:index.html.twig', [
            'items' => $requests,
            'type' => 'UserRequests',
            'folder' => $folder,
            'filter' => $request->query->all(),
            'submenu' => [
                'active' => 'requests_'.$folder,
                'route' => 'messages',
                'items' => $this->getSubMenuItems(),
            ],
        ]);
    }

    private function getSubMenuItems()
    {
        return [
            'requests_inbox' => [
                'key' => 'MessagesRequestsReceived',
                'url' => $this->generateUrl('requests', ['folder' => 'inbox']),
            ],
            'requests_sent' => [
                'key' => 'MessagesRequestsSent',
                'url' => $this->generateUrl('requests', ['folder' => 'sent']),
            ],
            'messages_inbox' => [
                'key' => 'MessagesReceived',
                'url' => $this->generateUrl('messages', ['folder' => 'inbox']),
            ],
            'messages_sent' => [
                'key' => 'MessagesSent',
                'url' => $this->generateUrl('messages', ['folder' => 'sent']),
            ],
            'messages_spam' => [
                'key' => 'MessagesSpam',
                'url' => $this->generateUrl('messages', ['folder' => 'spam']),
            ],
            'messages_deleted' => [
                'key' => 'MessagesDeleted',
                'url' => $this->generateUrl('messages', ['folder' => 'deleted']),
            ],
        ];
    }
}