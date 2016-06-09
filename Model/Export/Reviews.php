<?php
/**
 * Created by PhpStorm.
 * User: kevincarroll
 * Date: 6/7/16
 * Time: 11:39 AM
 */

namespace TurnTo\SocialCommerce\Model\Export;

class Reviews extends AbstractExport
{
    /**
     * Gets the collection of all reviews with an approved status
     *
     * @return \Magento\Review\Model\ResourceModel\Review\Collection
     */
    protected function getReviews()
    {
        $collection = $this->reviewCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter('entity_id',
                $this->reviewFactory->create()->getEntityIdByCode(\Magento\Review\Model\Review::ENTITY_PRODUCT_CODE))
            ->addFieldToFilter('status_id', \Magento\Review\Model\Review::STATUS_APPROVED);

        return $collection;
    }

    /**
     * Writes a TSV summary of all approved reviews formatted according to TurnTo historical reviews feed documentation
     *
     * @param $filePath
     */
    public function exportReviewsToFile($filePath)
    {
        $handle = fopen($filePath, 'w');
        try {
            fputcsv(
                $handle,
                ['SKU', 'ID', 'TITLE', 'TEXT', 'SUBMISSION TIME', 'USER EMAIL ADDRESS', 'RATING', 'USER NAME'],
                "\t"
            );
            $reviews = $this->getReviews();

            foreach ($reviews as $review) {
                $sku = null;
                $reviewId = null;
                $title = null;
                $detail = null;
                $createdAt = null;
                $userEmail = null;
                $avgRating = null;
                $userName = null;

                $reviewId = $review->getReviewId();
                $votes = $this->voteFactory->create()->getResourceCollection()->setReviewFilter($reviewId)->load();
                $rating = 0;
                $ratingCount = 0;

                foreach ($votes as $vote) {
                    $rating += (int)$vote->getValue();
                    $ratingCount++;
                }
                $avgRating = (int)round($rating / $ratingCount);

                $productEntityId = $review->getEntityPkValue();
                $sku = $this->productFactory->create()->load($productEntityId)->getSku();

                $customerId = $review->getCustomerId();
                if ($customerId) {
                    $customer = $this->customerFactory->create()->load($customerId);
                    $userEmail = $customer->getEmail();
                    $userName = $customer->getName();
                }

                $title = $review->getTitle();
                $detail = $review->getDetail();
                $createdAt = $review->getCreatedAt();

                if (!empty($sku) && $rating > 0 && $rating < 6) {
                    fputcsv(
                        $handle,
                        [$sku, $reviewId, $title, $detail, $createdAt, $userEmail, $avgRating, $userName],
                        "\t"
                    );
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('An error occurred while generating the review export for TurnTo', [ 'error' => $e]);
        } finally {
            fclose($handle);
        }
    }
}
