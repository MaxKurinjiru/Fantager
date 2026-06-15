<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\Auth\User;
use App\Entity\Community\Message;
use App\Entity\Team\Team;
use App\Service\Community\CommunityService;
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
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommunityService $communityService,
    ) {
    }

    #[Route('', name: 'api_messages_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->json(['error' => 'No team assigned.'], Response::HTTP_BAD_REQUEST);
        }

        $folder = $request->query->get('folder', 'inbox');

        if ('sent' === $folder) {
            $messages = $this->communityService->getSentMessages($team);
        } else {
            $messages = $this->communityService->getInboxMessages($team);
        }

        $data = array_map([$this, 'serializeMessage'], $messages);

        return $this->json($data);
    }

    #[Route('/unread-count', name: 'api_messages_unread_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->json(['error' => 'No team assigned.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['count' => $this->communityService->countUnreadInbox($team)]);
    }

    #[Route('/recipients', name: 'api_messages_recipients', methods: ['GET'])]
    public function recipients(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->json(['error' => 'No team assigned.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->communityService->getMessageRecipients($team));
    }

    #[Route('/{id}', name: 'api_messages_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->json(['error' => 'No team assigned.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var Message|null $message */
        $message = $this->em->getRepository(Message::class)->find($id);
        if (!$message) {
            return $this->json(['error' => 'Message not found.'], Response::HTTP_NOT_FOUND);
        }

        // Check ownership/permissions
        $isSender = $message->getSenderTeam() === $team && !$message->isDeletedBySender();
        $isReceiver = $message->getReceiverTeam() === $team && !$message->isDeletedByReceiver();

        if (!$isSender && !$isReceiver) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        // Mark as read if viewing recipient
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
        $team = $user->getTeam();
        if (!$team) {
            return $this->json(['error' => 'No team assigned.'], Response::HTTP_BAD_REQUEST);
        }

        $content = json_decode($request->getContent(), true) ?? [];
        $receiverTeamId = (int) ($content['receiver_team_id'] ?? 0);
        $subject = trim($content['subject'] ?? '');
        $body = trim($content['body'] ?? '');

        if (0 === $receiverTeamId || '' === $subject || '' === $body) {
            return $this->json(['error' => 'Receiver team ID, subject, and body are required.'], Response::HTTP_BAD_REQUEST);
        }

        $receiver = $this->em->getRepository(Team::class)->find($receiverTeamId);
        if (!$receiver) {
            return $this->json(['error' => 'Recipient team not found.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $message = $this->communityService->sendMessage($team, $receiver, $subject, $body);

            return $this->json($this->serializeMessage($message), Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'api_messages_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->json(['error' => 'No team assigned.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->communityService->deleteMessage($team, $id);

            return $this->json(['success' => true]);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
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
            'sender_team' => [
                'id' => $message->getSenderTeam()->getId(),
                'name' => $message->getSenderTeam()->getName(),
                'colors' => $message->getSenderTeam()->getColors(),
            ],
            'receiver_team' => [
                'id' => $message->getReceiverTeam()->getId(),
                'name' => $message->getReceiverTeam()->getName(),
                'colors' => $message->getReceiverTeam()->getColors(),
            ],
        ];
    }
}
