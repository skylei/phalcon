<?php

namespace Eva\EvaBlog\Models;

use Eva\EvaBlog\Entities;

class Tag extends Entities\Tags
{

    public function getPopularTags($limit = 10)
    {
        $tags = self::query()
            ->from(__CLASS__)
            ->columns(array(
                'id', 'tagName', 'COUNT(id) AS tagCount'
            ))
            ->leftJoin('Eva\EvaBlog\Entities\TagsPosts', 'id = r.tagId', 'r')
            ->groupBy('id')
            ->orderBy('COUNT(id) DESC')
            ->limit($limit)
            ->cache(array(
                "lifetime" => 3600 * 24,
                "key" => "popular-tags-$limit"
            ));
        return $tags;
    }

    public function getRelatedPosts($postId, $limit = 10)
    {
        $phql = <<<QUERY
SELECT B.postId, B.tagId, SUM( LOG( 100 / C.count ) ) AS weight
FROM Eva\EvaBlog\Entities\TagsPosts AS A
LEFT JOIN Eva\EvaBlog\Entities\TagsPosts AS B ON A.tagId = B.tagId
LEFT JOIN Eva\EvaBlog\Entities\Tags AS C ON B.tagId = C.id
WHERE A.postId = $postId
AND B.postId != $postId
GROUP BY B.postId
ORDER BY weight DESC
LIMIT $limit
QUERY;
        $manager = $this->getModelsManager();
        $query = $manager->createQuery($phql);
        $results = $query->execute();
        $posts = array();
        if($results->count() > 0) {
            foreach($results as $result) {
                
            }
        }
    }
}
