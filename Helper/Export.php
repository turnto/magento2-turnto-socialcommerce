<?php
/**
 * @category    ClassyLlama
 * @copyright   Copyright (c) 2018 Classy Llama Studios, LLC
 * @author      sean.templeton
 */

namespace TurnTo\SocialCommerce\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Export extends AbstractHelper
{
    /**
     * Gets the deepest tree for given product and returns as "rootNodeName > branchNodeName > leafNodeName"
     *
     * @param \Magento\Catalog\Model\Product $product
     *
     * @return string
     */
    public function getCategoryTreeString(\Magento\Catalog\Model\Product $product)
    {
        $categoryName = '';
        $categories = $product->getCategoryCollection();
        $deepestLength = 0;
        $deepestTree = [];

        foreach ($categories as $category) {
            $tempTree = $this->getCategoryBranch($category);
            $treeLength = count($tempTree);
            if ($treeLength > $deepestLength) {
                $deepestLength = $treeLength;
                $deepestTree = $tempTree;
            }
        }

        foreach (array_reverse($deepestTree) as $node) {
            $nodeName = $node->getName();
            if (!empty($nodeName)) {
                if (!empty($categoryName)) {
                    $categoryName .= ' > ';
                }
                $categoryName .= $node->getName();
            }
        }

        return $categoryName;
    }

    /**
     * Recursively walks category chain from leaf to root while writing the traversed branch to an array
     *
     * @param \Magento\Catalog\Model\Category $category
     * @param array                           $categoryBranch
     *
     * @return array
     */
    private function getCategoryBranch(\Magento\Catalog\Model\Category $category, array $categoryBranch = [])
    {
        try {
            $parent = $category->getParentCategory();
        } catch (\Magento\Framework\Exception\NoSuchEntityException $isRootEntity) {
            $parent = null;
        } finally {
            $categoryBranch[] = $category;
            if (isset($parent)) {
                return $this->getCategoryBranch($parent, $categoryBranch);
            } else {
                return $categoryBranch;
            }
        }
    }

    /**
     * Get item group ID for a given product
     *
     * @param \Magento\Catalog\Model\Product      $product
     * @param \Magento\Catalog\Model\Product|bool $parent
     *
     * @return string|bool
     */
    public function getItemGroupId($product, $parent)
    {
        if ($parent) {
            return $parent->getSku();
        } else {
            return $product->getSku();
        }
    }

    /**
     * Escapes special characters for xml use
     *
     * @param string $dirtyString
     *
     * @return string
     */
    public function sanitizeData($dirtyString)
    {
        $replacementMap = [
            '&' => '&amp;',
            '"' => '&quot;',
            '\'' => '&apos;',
            '<' => '&lt;',
            '>' => '&gt;',
        ];

        return str_replace(array_keys($replacementMap), array_values($replacementMap), $dirtyString);
    }
}