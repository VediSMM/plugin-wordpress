<?php

declare(strict_types=1);

namespace VediSMM\WordPress\Domain;

final readonly class DraftInput
{
    public string $title;
    public string $content;
    public ?string $link;
    /** @var array<int,int> */
    public array $accountIds;
    /** @var array<int,int> */
    public array $groupIds;
    /** @var array<int,int> */
    public array $mediaIds;
    public bool $shortenLinks;
    public bool $addSource;

    /** @param array<int,mixed> $accountIds @param array<int,mixed> $groupIds @param array<int,mixed> $mediaIds */
    public function __construct(
        string $title,
        string $content,
        ?string $link,
        array $accountIds,
        array $groupIds,
        array $mediaIds,
        bool $shortenLinks = false,
        bool $addSource = false
    ) {
        $this->title = Normalizer::title($title);
        $this->content = Normalizer::text($content);
        $this->link = Normalizer::url($link);
        $this->accountIds = Normalizer::positiveUniqueIds($accountIds);
        $this->groupIds = Normalizer::positiveUniqueIds($groupIds);
        $this->mediaIds = Normalizer::positiveUniqueIds($mediaIds);
        $this->shortenLinks = $shortenLinks;
        $this->addSource = $shortenLinks && $addSource;
    }

    /** @return array{title:string,content:string,link:?string,account_ids:array<int,int>,group_ids:array<int,int>,media_ids:array<int,int>,options:array{tracking:array{shorten_links:bool,add_source:bool}}} */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'link' => $this->link,
            'account_ids' => $this->accountIds,
            'group_ids' => $this->groupIds,
            'media_ids' => $this->mediaIds,
            'options' => [
                'tracking' => [
                    'shorten_links' => $this->shortenLinks,
                    'add_source' => $this->addSource,
                ],
            ],
        ];
    }
}
