<?php

declare(strict_types=1);

/* For licensing terms, see /license.txt */

namespace Chamilo\CoreBundle\Controller\Api;

use Chamilo\CoreBundle\Entity\User;
use Chamilo\CoreBundle\Helpers\AiDisclosureHelper;
use Chamilo\CoreBundle\Helpers\CourseHelper;
use Chamilo\CoreBundle\Repository\Node\CourseRepository;
use Chamilo\CourseBundle\Entity\CDocument;
use Chamilo\CourseBundle\Repository\CDocumentRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

    private function normalizeCloudLinkUrl(string $url, TranslatorInterface $translator): string
    {
        $url = trim($url);

        if ('' === $url) {
            throw new BadRequestHttpException($translator->trans('The URL is required.'));
        }

        $parts = parse_url($url);

        if (!\is_array($parts)) {
            throw new BadRequestHttpException($translator->trans('Invalid URL.'));
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!\in_array($scheme, ['http', 'https'], true)) {
            throw new BadRequestHttpException($translator->trans('Only HTTP and HTTPS URLs are allowed.'));
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ('' === $host) {
            throw new BadRequestHttpException($translator->trans('Invalid URL host.'));
        }

        if (!$this->isAllowedCloudLinkHost($host)) {
            throw new BadRequestHttpException($translator->trans('This cloud provider is not allowed.'));
        }

        return $url;
    }

    private function isAllowedCloudLinkHost(string $host): bool
    {
        foreach (self::ALLOWED_CLOUD_LINK_HOSTS as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost)) {
                return true;
            }
        }

        return false;
    }

    private function assertUserCanLinkToCourses(
        Request $request,
        Security $security,
        CourseRepository $courseRepository,
    ): void {
        $user = $security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        if ($security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $resourceLinkList = $this->extractResourceLinkList($request);
        foreach ($resourceLinkList as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $cid = (int) ($entry['cid'] ?? 0);
            if ($cid <= 0) {
                continue;
            }

            $course = $courseRepository->find($cid);
            if (null === $course || !$course->hasUserAsTeacher($user)) {
                throw new AccessDeniedHttpException('You are not a teacher of one of the referenced courses.');
            }
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function extractResourceLinkList(Request $request): array
    {
        $raw = (string) $request->getContent();
        if ('' !== $raw) {
            $decoded = json_decode($raw, true);
            if (\is_array($decoded) && isset($decoded['resourceLinkList']) && \is_array($decoded['resourceLinkList'])) {
                return $decoded['resourceLinkList'];
            }
        }

        $fromForm = $request->get('resourceLinkList', []);
        if (\is_array($fromForm)) {
            return $fromForm;
        }

        if (\is_string($fromForm) && '' !== $fromForm) {
            $normalized = str_contains($fromForm, '[') ? $fromForm : '['.$fromForm.']';
            $decoded = json_decode($normalized, true);
            if (\is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
