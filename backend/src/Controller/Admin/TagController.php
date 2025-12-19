<?php

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Dto\Admin\CreateTagRequest;
use App\Dto\Admin\Query\TagListQuery;
use App\Dto\Admin\UpdateTagRequest;
use App\Dto\Admin\UpdateStatusRequest;
use App\Entity\Tag;
use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/admin/tags')]
#[AdminOnly]
class TagController extends AbstractController
{
    public function __construct(
        private TagRepository $tagRepository,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_tag_list', methods: ['GET'])]
    public function list(#[MapQueryString] TagListQuery $query = new TagListQuery()): JsonResponse
    {
        $result = $this->tagRepository->findPaginated(
            $query->getPage(),
            $query->getLimit(),
            $query->toFilters()
        );

        return $this->json([
            'data' => array_map(fn(Tag $t) => $this->serializeTag($t), $result['data']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    #[Route('/{id}', name: 'admin_tag_detail', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $tag = $this->tagRepository->find($id);
        if (!$tag) {
            return $this->json(['error' => $this->translator->trans('admin.tag.not_found')], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeTag($tag, true));
    }

    #[Route('', name: 'admin_tag_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateTagRequest $dto): JsonResponse
    {
        // 生成或使用提供的 slug
        $slug = $dto->slug ?? $this->generateSlug($dto->name);

        // 检查 slug 是否已存在
        if ($this->tagRepository->existsBySlug($slug)) {
            return $this->json(['error' => $this->translator->trans('admin.tag.slug_exists')], Response::HTTP_CONFLICT);
        }

        $tag = new Tag();
        $tag->setName($dto->name);
        $tag->setSlug($slug);

        if ($dto->color !== null) {
            $tag->setColor($dto->color);
        }
        if ($dto->sortOrder !== null) {
            $tag->setSortOrder($dto->sortOrder);
        }
        if ($dto->isActive !== null) {
            $tag->setIsActive($dto->isActive);
        }

        $this->tagRepository->save($tag, true);

        return $this->json([
            'message' => $this->translator->trans('admin.tag.created'),
            'tag' => $this->serializeTag($tag, true),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'admin_tag_update', methods: ['PUT'])]
    public function update(string $id, #[MapRequestPayload] UpdateTagRequest $dto): JsonResponse
    {
        $tag = $this->tagRepository->find($id);
        if (!$tag) {
            return $this->json(['error' => $this->translator->trans('admin.tag.not_found')], Response::HTTP_NOT_FOUND);
        }

        if ($dto->name !== null) {
            $tag->setName($dto->name);
        }
        if ($dto->slug !== null) {
            // 检查 slug 是否已被其他标签使用
            if ($this->tagRepository->existsBySlug($dto->slug, $tag->getId())) {
                return $this->json(['error' => $this->translator->trans('admin.tag.slug_exists')], Response::HTTP_CONFLICT);
            }
            $tag->setSlug($dto->slug);
        }
        if ($dto->color !== null) {
            $tag->setColor($dto->color);
        }
        if ($dto->sortOrder !== null) {
            $tag->setSortOrder($dto->sortOrder);
        }
        if ($dto->isActive !== null) {
            $tag->setIsActive($dto->isActive);
        }

        $this->tagRepository->save($tag, true);

        return $this->json([
            'message' => $this->translator->trans('admin.tag.updated'),
            'tag' => $this->serializeTag($tag, true),
        ]);
    }

    #[Route('/{id}', name: 'admin_tag_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $tag = $this->tagRepository->find($id);
        if (!$tag) {
            return $this->json(['error' => $this->translator->trans('admin.tag.not_found')], Response::HTTP_NOT_FOUND);
        }

        // 检查是否有关联的商品
        if ($tag->getProducts()->count() > 0) {
            return $this->json([
                'error' => $this->translator->trans('admin.tag.has_products'),
                'productCount' => $tag->getProducts()->count(),
            ], Response::HTTP_CONFLICT);
        }

        $this->tagRepository->remove($tag, true);

        return $this->json(['message' => $this->translator->trans('admin.tag.deleted')]);
    }

    #[Route('/{id}/status', name: 'admin_tag_status', methods: ['PUT'])]
    public function updateStatus(string $id, #[MapRequestPayload] UpdateStatusRequest $dto): JsonResponse
    {
        $tag = $this->tagRepository->find($id);
        if (!$tag) {
            return $this->json(['error' => $this->translator->trans('admin.tag.not_found')], Response::HTTP_NOT_FOUND);
        }

        $tag->setIsActive($dto->isActive);
        $this->tagRepository->save($tag, true);

        return $this->json([
            'message' => $dto->isActive ? $this->translator->trans('admin.tag.activated') : $this->translator->trans('admin.tag.deactivated'),
            'tag' => $this->serializeTag($tag),
        ]);
    }

    private function serializeTag(Tag $tag, bool $detail = false): array
    {
        $data = [
            'id' => $tag->getId(),
            'name' => $tag->getName(),
            'slug' => $tag->getSlug(),
            'color' => $tag->getColor(),
            'sortOrder' => $tag->getSortOrder(),
            'isActive' => $tag->isActive(),
            'createdAt' => $tag->getCreatedAt()->format('c'),
        ];

        if ($detail) {
            $data['productCount'] = $tag->getProducts()->count();
        }

        return $data;
    }

    private function generateSlug(string $name): string
    {
        $slugger = new AsciiSlugger();
        return strtolower($slugger->slug($name)->toString());
    }
}
