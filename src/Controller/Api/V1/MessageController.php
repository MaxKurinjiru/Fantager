<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
use App\Entity\Auth\User;
use App\Entity\Community\Message;
use App\Service\Community\CommunityService;
use App\Service\Community\ForumThreadHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/messages')]
#[IsGranted('ROLE_PLAYER')]
class MessageController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommunityService $communityService,
        private readonly ForumThreadHelper $authorHelper,
    ) {
    }

    #[Route('', name: 'api_messages_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $folder = $request->query->get('folder', 'inbox');

        if ('sent' === $folder) {
            $messages = $this->communityService->getSentMessages($user);
        } else {
            $messages = $this->communityService->getInboxMessages($user);
        }

        $data = array_map([$this, 'serializeMessage'], $messages);

        return $this->json($data);
    }

    #[Route('/unread-count', name: 'api_messages_unread_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json(['count' => $this->communityService->countUnreadInbox($user)]);
    }

    #[Route('/recipients', name: 'api_messages_recipients', methods: ['GET'])]
    public function recipients(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->communityService->getMessageRecipients($user));
    }

    #[Route('/{id}', name: 'api_messages_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        /** @var Message|null $message */
        $message = $this->em->getRepository(Message::class)->find($id);
        if (!$message) {
            return $this->jsonError('error.message_not_found', 404);
        }

        $isSender = $message->getSenderUser() === $user && !$message->isDeletedBySender();
        $isReceiver = $message->getReceiverUser() === $user && !$message->isDeletedByReceiver();

        if (!$isSender && !$isReceiver) {
            return $this->jsonError('error.access_denied', 403);
        }

        if ($isReceiver) {
            $this->communityService->markMessageAsRead($message);
        }

        return $this->json($this->serializeMessage($message));
    }

    #[Route('', name: 'api_messages_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $content = json_decode($request->getContent(), true) ?? [];
        $receiverUserId = (int) ($content['receiver_user_id'] ?? 0);
        $subject = trim($content['subject'] ?? '');
        $body = trim($content['body'] ?? '');

        if (0 === $receiverUserId || '' === $subject || '' === $body) {
            return $this->jsonError('error.message_fields_required', 400);
        }

        /** @var User|null $receiver */
        $receiver = $this->em->getRepository(User::class)->find($receiverUserId);
        if (!$receiver) {
            return $this->jsonError('error.recipient_not_found', 400);
        }

        try {
            $message = $this->communityService->sendMessage($user, $receiver, $subject, $body);

            return $this->json($this->serializeMessage($message), Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 400);
        }
    }

    #[Route('/{id}', name: 'api_messages_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $this->communityService->deleteMessage($user, $id);

            return $this->json(['success' => true]);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 400);
        }
    }

    /** @return array<string, mixed> */
    private function serializeMessage(Message $message): array
    {
        return [
            'id' => $message->getId(),
            'subject' => $message->getSubject(),
            'body' => $message->getBody(),
            'sentAt' => $message->getSentAt()->format(\DateTimeInterface::ATOM),
            'readAt' => $message->getReadAt()?->format(\DateTimeInterface::ATOM),
            'sender' => $this->authorHelper->serializeAuthor(
                $message->getSenderUser(),
                $message->getSenderTeam(),
            ),
            'receiver' => $this->authorHelper->serializeAuthor(
                $message->getReceiverUser(),
                $message->getReceiverTeam(),
            ),
        ];
    }
}
