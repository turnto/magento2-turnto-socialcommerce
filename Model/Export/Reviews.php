<?php
/**
 * TurnTo_SocialCommerce
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright  Copyright (c) 2016 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace TurnTo\SocialCommerce\Model\Export;

class Reviews extends AbstractExport
{
    /**
     * @var \Magento\Review\Model\ReviewFactory|null
     */
    protected $reviewFactory = null;

    /**
     * @var \Magento\Review\Model\ResourceModel\Review\CollectionFactory|null
     */
    protected $reviewCollectionFactory = null;

    /**
     * @var \Magento\Review\Model\Rating\Option\VoteFactory|null
     */
    protected $voteFactory = null;

    /**
     * @var \Magento\Catalog\Model\ProductFactory|null
     */
    protected $productFactory = null;

    /**
     * Reviews constructor.
     *
     * @param \TurnTo\SocialCommerce\Helper\Config $config
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \TurnTo\SocialCommerce\Logger\Monolog $logger
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Framework\Intl\DateTimeFactory $dateTimeFactory
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Framework\Api\FilterBuilder $filterBuilder
     * @param \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder
     * @param \Magento\UrlRewrite\Model\UrlFinderInterface $urlFinder
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Review\Model\ReviewFactory $reviewFactory
     * @param \Magento\Review\Model\ResourceModel\Review\CollectionFactory $reviewCollectionFactory
     * @param \Magento\Review\Model\Rating\Option\VoteFactory $voteFactory
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     */
    public function __construct(
        \TurnTo\SocialCommerce\Helper\Config $config,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \TurnTo\SocialCommerce\Logger\Monolog $logger,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\Intl\DateTimeFactory $dateTimeFactory,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder,
        \Magento\UrlRewrite\Model\UrlFinderInterface $urlFinder,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Review\Model\ReviewFactory $reviewFactory,
        \Magento\Review\Model\ResourceModel\Review\CollectionFactory $reviewCollectionFactory,
        \Magento\Review\Model\Rating\Option\VoteFactory $voteFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory
    ) {
        $this->reviewFactory = $reviewFactory;
        $this->reviewCollectionFactory = $reviewCollectionFactory;
        $this->voteFactory = $voteFactory;
        $this->productFactory = $productFactory;
        
        parent::__construct(
            $config,
            $productCollectionFactory,
            $logger,
            $encryptor,
            $dateTimeFactory,
            $searchCriteriaBuilder,
            $filterBuilder,
            $sortOrderBuilder,
            $urlFinder,
            $storeManager
        );
    }

    /**
     * Gets the collection of all reviews with an approved status
     *
     * @return \Magento\Review\Model\ResourceModel\Review\Collection
     */
    protected function getReviews()
    {
        $collection = $this->reviewCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter(
                'entity_id',
                $this->reviewFactory->create()->getEntityIdByCode(\Magento\Review\Model\Review::ENTITY_PRODUCT_CODE)
            )
            ->addFieldToFilter('status_id', \Magento\Review\Model\Review::STATUS_APPROVED);

        return $collection;
    }

    /**
     * Writes a TSV summary of all approved reviews formatted according to TurnTo historical reviews feed API
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
                $userName = null;

                $reviewId = $review->getReviewId();
                $ratingInformation = $this->getRatingInformation($reviewId);
                if ($ratingInformation['count'] === 0) {
                    continue;
                }
                $averageRating = (int)round($ratingInformation['rating'] / $ratingInformation['count']);

                $productEntityId = $review->getEntityPkValue();
                $sku = $this->productFactory->create()->load($productEntityId)->getSku();
                if (empty($sku)) {
                    continue;
                }

                $user = $this->getCustomerInformationFromReview($review);

                $title = $review->getTitle();
                $detail = $review->getDetail();
                $createdAt = $review->getCreatedAt();

                fputcsv(
                    $handle,
                    [$sku, $reviewId, $title, $detail, $createdAt, $user['email'], $averageRating, $user['name']],
                    "\t"
                );
            }
        } catch (\Exception $e) {
            $this->logger->error('An error occurred while generating the review export for TurnTo', [ 'error' => $e]);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param $reviewId
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getRatingInformation($reviewId) {
        $rating = 0;
        $ratingCount = 0;

        $votes = $this->voteFactory->create()->getResourceCollection()->setReviewFilter($reviewId)->load();

        foreach ($votes as $vote) {
            $rating += (int)$vote->getValue();
            $ratingCount++;
        }

        return ['rating' => $rating, 'count' => $ratingCount];
    }

    /**
     * @param $review
     * @return array
     */
    protected function getCustomerInformationFromReview($review)
    {
        $customerId = null;
        $userEmail = '';
        $userName = '';

        $customerId = $review->getCustomerId();
        if (!empty($customerId)) {
            $customer = $this->customerFactory->create()->load($customerId);
            $userEmail = $customer->getEmail();
            $userName = $customer->getName();
        }

        return ['id' => $customerId, 'email' => $userEmail, 'name' => $userName];
    }
}
