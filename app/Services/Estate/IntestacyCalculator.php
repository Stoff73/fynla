<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\FamilyMember;
use App\Models\User;

class IntestacyCalculator
{
    private const THRESHOLD_SPOUSE_CHILDREN = 322000;

    /**
     * Calculate how estate would be distributed under UK intestacy rules
     */
    public function calculateDistribution(int $userId, float $estateValue): array
    {
        $user = User::find($userId);

        if (! $user) {
            throw new \Exception('User not found');
        }

        // Check if married - either by spouse_id OR by having a spouse in family_members
        $hasLinkedSpouse = in_array($user->marital_status, ['married', 'civil_partnership']) && $user->spouse_id !== null;
        $familyMembers = FamilyMember::where('user_id', $userId)->get();
        $hasSpouseFamilyMember = $familyMembers->where('relationship', 'spouse')->count() > 0;

        $isMarried = $hasLinkedSpouse || $hasSpouseFamilyMember;

        $children = $familyMembers->where('relationship', 'child')->count();
        $parents = $familyMembers->where('relationship', 'parent')->count();
        $siblings = $familyMembers->whereIn('relationship', ['sibling', 'brother', 'sister'])->count();
        $halfSiblings = $familyMembers->where('relationship', 'half-sibling')->count();
        $grandparents = $familyMembers->where('relationship', 'grandparent')->count();
        $auntsUncles = $familyMembers->whereIn('relationship', ['aunt', 'uncle'])->count();
        $auntsUnclesHalfBlood = $familyMembers->where('relationship', 'aunt-uncle-half-blood')->count();

        $decisionPath = [];
        $beneficiaries = [];
        $scenario = '';
        $explanation = '';
        $goesToCrown = false;

        // Decision tree logic based on UK intestacy rules
        $decisionPath[] = [
            'question' => 'Are you married/civil partnered?',
            'answer' => $isMarried ? 'YES' : 'NO',
        ];

        if ($isMarried) {
            $decisionPath[] = [
                'question' => 'Do you have any children?',
                'answer' => $children > 0 ? 'YES' : 'NO',
            ];

            if ($children > 0) {
                // Married with children
                $decisionPath[] = [
                    'question' => 'Is your estate worth more than £'.number_format(self::THRESHOLD_SPOUSE_CHILDREN).'?',
                    'answer' => $estateValue > self::THRESHOLD_SPOUSE_CHILDREN ? 'YES' : 'NO',
                ];

                if ($estateValue > self::THRESHOLD_SPOUSE_CHILDREN) {
                    // Spouse gets first £250k + half of rest, children get other half
                    $spouseAmount = self::THRESHOLD_SPOUSE_CHILDREN + (($estateValue - self::THRESHOLD_SPOUSE_CHILDREN) / 2);
                    $childrenAmount = ($estateValue - self::THRESHOLD_SPOUSE_CHILDREN) / 2;
                    $perChildAmount = $childrenAmount / $children;

                    $scenario = 'Married with Children - Estate over £322,000';
                    $explanation = 'Your spouse/civil partner receives the first £322,000 plus half of the remaining estate. Your children share the other half equally.';

                    $beneficiaries[] = [
                        'relationship' => 'Spouse/Civil Partner',
                        'name' => null,
                        'amount' => $spouseAmount,
                        'percentage' => round(($spouseAmount / $estateValue) * 100, 2),
                        'share_description' => 'First £322,000 + half of remaining (£'.number_format($estateValue - self::THRESHOLD_SPOUSE_CHILDREN).')',
                        'count' => 1,
                    ];

                    $beneficiaries[] = [
                        'relationship' => 'Children',
                        'name' => null,
                        'amount' => $childrenAmount,
                        'percentage' => round(($childrenAmount / $estateValue) * 100, 2),
                        'share_description' => 'Half of remaining estate, £'.number_format($perChildAmount).' each',
                        'count' => $children,
                    ];
                } else {
                    // Estate <= £250k, spouse gets all
                    $scenario = 'Married with Children - Estate under £322,000';
                    $explanation = 'Because your estate is worth less than £322,000, your spouse/civil partner inherits everything.';

                    $beneficiaries[] = [
                        'relationship' => 'Spouse/Civil Partner',
                        'name' => null,
                        'amount' => $estateValue,
                        'percentage' => 100,
                        'share_description' => 'Entire estate',
                        'count' => 1,
                    ];
                }
            } else {
                // Married with no children - spouse gets all
                $scenario = 'Married without Children';
                $explanation = 'Your spouse/civil partner inherits your entire estate.';

                $beneficiaries[] = [
                    'relationship' => 'Spouse/Civil Partner',
                    'name' => null,
                    'amount' => $estateValue,
                    'percentage' => 100,
                    'share_description' => 'Entire estate',
                    'count' => 1,
                ];
            }
        } else {
            // Not married - check for children
            $decisionPath[] = [
                'question' => 'Do you have any children?',
                'answer' => $children > 0 ? 'YES' : 'NO',
            ];

            if ($children > 0) {
                // Children inherit everything equally
                $scenario = 'Not Married - Children Inherit';
                $explanation = 'Your children share your entire estate equally.';
                $perChildAmount = $estateValue / $children;

                $beneficiaries[] = [
                    'relationship' => 'Children',
                    'name' => null,
                    'amount' => $estateValue,
                    'percentage' => 100,
                    'share_description' => '£'.number_format($perChildAmount).' each',
                    'count' => $children,
                ];
            } else {
                // No children - check parents
                $decisionPath[] = [
                    'question' => 'Do you have living parents?',
                    'answer' => $parents > 0 ? 'YES' : 'NO',
                ];

                if ($parents > 0) {
                    // Parents inherit everything equally
                    $scenario = 'No Children - Parents Inherit';
                    $explanation = 'Your parents share your entire estate equally.';
                    $perParentAmount = $estateValue / $parents;

                    $beneficiaries[] = [
                        'relationship' => 'Parents',
                        'name' => null,
                        'amount' => $estateValue,
                        'percentage' => 100,
                        'share_description' => $parents > 1 ? '£'.number_format($perParentAmount).' each' : 'Entire estate',
                        'count' => $parents,
                    ];
                } else {
                    // No parents - check siblings
                    $decisionPath[] = [
                        'question' => 'Do you have brothers/sisters?',
                        'answer' => $siblings > 0 ? 'YES' : 'NO',
                    ];

                    if ($siblings > 0) {
                        $scenario = 'No Children or Parents - Siblings Inherit';
                        $explanation = 'Your brothers and sisters share your entire estate equally.';
                        $perSiblingAmount = $estateValue / $siblings;

                        $beneficiaries[] = [
                            'relationship' => 'Siblings',
                            'name' => null,
                            'amount' => $estateValue,
                            'percentage' => 100,
                            'share_description' => '£'.number_format($perSiblingAmount).' each',
                            'count' => $siblings,
                        ];
                    } else {
                        // No siblings - check half-siblings
                        $decisionPath[] = [
                            'question' => 'Do you have half brothers/sisters?',
                            'answer' => $halfSiblings > 0 ? 'YES' : 'NO',
                        ];

                        if ($halfSiblings > 0) {
                            $scenario = 'No Siblings - Half-Siblings Inherit';
                            $explanation = 'Your half-brothers and half-sisters share your entire estate equally.';
                            $perHalfSiblingAmount = $estateValue / $halfSiblings;

                            $beneficiaries[] = [
                                'relationship' => 'Half-Siblings',
                                'name' => null,
                                'amount' => $estateValue,
                                'percentage' => 100,
                                'share_description' => '£'.number_format($perHalfSiblingAmount).' each',
                                'count' => $halfSiblings,
                            ];
                        } else {
                            // No half-siblings - check grandparents
                            $decisionPath[] = [
                                'question' => 'Do you have living grandparents?',
                                'answer' => $grandparents > 0 ? 'YES' : 'NO',
                            ];

                            if ($grandparents > 0) {
                                $scenario = 'Grandparents Inherit';
                                $explanation = 'Your grandparents share your entire estate equally.';
                                $perGrandparentAmount = $estateValue / $grandparents;

                                $beneficiaries[] = [
                                    'relationship' => 'Grandparents',
                                    'name' => null,
                                    'amount' => $estateValue,
                                    'percentage' => 100,
                                    'share_description' => '£'.number_format($perGrandparentAmount).' each',
                                    'count' => $grandparents,
                                ];
                            } else {
                                // No grandparents - check aunts/uncles
                                $decisionPath[] = [
                                    'question' => 'Do you have aunts/uncles?',
                                    'answer' => $auntsUncles > 0 ? 'YES' : 'NO',
                                ];

                                if ($auntsUncles > 0) {
                                    $scenario = 'Aunts and Uncles Inherit';
                                    $explanation = 'Your aunts and uncles share your entire estate equally.';
                                    $perAuntUncleAmount = $estateValue / $auntsUncles;

                                    $beneficiaries[] = [
                                        'relationship' => 'Aunts and Uncles',
                                        'name' => null,
                                        'amount' => $estateValue,
                                        'percentage' => 100,
                                        'share_description' => '£'.number_format($perAuntUncleAmount).' each',
                                        'count' => $auntsUncles,
                                    ];
                                } else {
                                    // No aunts/uncles - check half-blood aunts/uncles
                                    $decisionPath[] = [
                                        'question' => "Do you have aunts/uncles 'of the half blood'?",
                                        'answer' => $auntsUnclesHalfBlood > 0 ? 'YES' : 'NO',
                                    ];

                                    if ($auntsUnclesHalfBlood > 0) {
                                        $scenario = 'Half-Blood Aunts and Uncles Inherit';
                                        $explanation = "Your aunts and uncles of the half blood (your parent's half-siblings) share your entire estate equally.";
                                        $perAmount = $estateValue / $auntsUnclesHalfBlood;

                                        $beneficiaries[] = [
                                            'relationship' => 'Aunts/Uncles (Half Blood)',
                                            'name' => null,
                                            'amount' => $estateValue,
                                            'percentage' => 100,
                                            'share_description' => '£'.number_format($perAmount).' each',
                                            'count' => $auntsUnclesHalfBlood,
                                        ];
                                    } else {
                                        // No relatives - goes to Crown
                                        $scenario = 'No Eligible Relatives - Crown Inherits';
                                        $explanation = 'With no eligible relatives, your entire estate passes to the Crown (government).';
                                        $goesToCrown = true;

                                        $beneficiaries[] = [
                                            'relationship' => 'The Crown (Government)',
                                            'name' => null,
                                            'amount' => $estateValue,
                                            'percentage' => 100,
                                            'share_description' => 'Entire estate (bona vacantia)',
                                            'count' => 1,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return [
            'scenario' => $scenario,
            'explanation' => $explanation,
            'estate_value' => $estateValue,
            'beneficiaries' => $beneficiaries,
            'decision_path' => $decisionPath,
            'goes_to_crown' => $goesToCrown,
        ];
    }
}
