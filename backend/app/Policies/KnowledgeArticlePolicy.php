<?php

namespace App\Policies;

use App\Models\KnowledgeArticle;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class KnowledgeArticlePolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('knowledge.view');
    }

    public function view(User $user, KnowledgeArticle $article): bool
    {
        return $user->can('knowledge.view')
            && ($article->organization_id === null || $this->sameOrganization($user, $article));
    }

    public function create(User $user): bool
    {
        return $user->can('knowledge.create');
    }

    public function update(User $user, KnowledgeArticle $article): bool
    {
        return $user->can('knowledge.update')
            && $article->organization_id !== null
            && $this->sameOrganization($user, $article);
    }

    public function delete(User $user, KnowledgeArticle $article): bool
    {
        return $user->can('knowledge.delete')
            && $article->organization_id !== null
            && $this->sameOrganization($user, $article);
    }
}
