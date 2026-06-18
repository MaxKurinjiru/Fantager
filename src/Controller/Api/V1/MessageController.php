<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
use App\Entity\Auth\User;
use App\Service\Community\CommunityService;
use App\Service\Community\ForumThreadHelper;
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

        $data = array_map(
            fn ($message) => $this->communityService->serializeMessage($message, $this->authorHelper),
            $messages,
        );

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

        try {
            $message = $this->communityService->getMessageForUser($user, $id);
        } catch (\DomainException $e) {
            $status = 'error.access_denied' === $e->getMessage() ? 403 : 404;

            return $this->jsonException($e, $status);
        }

        return $this->json($this->communityService->serializeMessage($message, $this->authorHelper));
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

        try {
            $receiver = $this->communityService->findMessageRecipient($user, $receiverUserId);
            $message = $this->communityService->sendMessage($user, $receiver, $subject, $body);

            return $this->json(
                $this->communityService->serializeMessage($message, $this->authorHelper),
                Response::HTTP_CREATED,
            );
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
}
