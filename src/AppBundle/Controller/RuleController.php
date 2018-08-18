<?php
namespace AppBundle\Controller;

use AppBundle\Form\AddMarketGroupRuleForm;
use AppBundle\Form\AddRoleRuleForm;
use AppBundle\Form\TestRuleForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Model\GroupRuleModel;
use AppBundle\Model\TypeRuleModel;
use AppBundle\Entity\RuleEntity;
use AppBundle\Form\AddGroupRuleForm;
use AppBundle\Form\AddTypeRuleForm;

class RuleController extends Controller
{
    /**
     * @Route("/system/admin/settings/rules", name="admin_buyback_rules")
     */
    public function buybackAction(Request $request)
    {
        return $this->action($request, 'P');
    }


    public function action(Request $request, string $ruleType)
    {
        $results = null;

        // Create Forms for the Page
        $roleForm = $this->createForm(AddRoleRuleForm::class);
        $groupForm = $this->createForm(AddGroupRuleForm::class);
        $marketGroupForm = $this->createForm(AddMarketGroupRuleForm::class);
        $typeForm = $this->createForm(AddTypeRuleForm::class);
        $testForm = $this->createForm(TestRuleForm::class);

        $em = $this->getDoctrine()->getManager();

        // Process POSTs
        if($request->getMethod() == "POST")
        {
            $form_results = null;
            $rule = new RuleEntity();
            $rule->setRuleType($ruleType);

            if($request->request->has('test_rule_form'))
            {
                $form_results = $request->request->get('test_rule_form');
                $results = $this->get('market')->getMergedBuybackRuleForType($form_results['typeid']);
            }
            else
            {
                if ($request->request->has('add_type_rule_form'))
                {
                    $form_results = $request->request->get('add_type_rule_form');
                    $rule->setTarget('type');
                    $rule->setTargetId($form_results['typeid']);
                    $rule->setTargetName($this->getDoctrine()->getRepository('AppBundle:SDE\TypeEntity')
                        ->findOneByTypeID($form_results['typeid'])->getTypeName());
                }
                elseif ($request->request->has('add_group_rule_form'))
                {
                    $form_results = $request->request->get('add_group_rule_form');
                    $rule->setTarget('group');
                    $rule->setTargetId($form_results['groupid']);
                    $rule->setTargetName($this->getDoctrine()->getRepository('AppBundle:SDE\GroupsEntity')
                        ->findOneByGroupID($form_results['groupid'])->getGroupName());
                }
                elseif ($request->request->has('add_market_group_rule_form'))
                {
                    $form_results = $request->request->get('add_market_group_rule_form');
                    $rule->setTarget('marketgroup');
                    $rule->setTargetId($form_results['marketgroupid']);
                    $rule->setTargetName($this->getDoctrine()->getRepository('AppBundle:SDE\MarketGroupsEntity')
                        ->findOneByMarketGroupID($form_results['marketgroupid'])->getMarketGroupName());
                }
                elseif ($request->request->has('add_role_rule_form'))
                {
                    $form_results = $request->request->get('add_role_rule_form');
                    $rule->setTarget('role');
                    $rule->setTargetId(0);
                    $rule->setTargetName($form_results['role']);
                }

                $rule->setSort($this->getDoctrine()->getRepository('AppBundle:RuleEntity', 'default')->getNextSort($ruleType));
                $rule->setAttribute($form_results['attribute']);

                $isValid = false;

                if ($rule->getAttribute() == 'canbuy' | $rule->getAttribute() == 'isrefined')
                {
                    if (preg_match('/^yes$|^true$/mi', $form_results['value']))
                    {
                        $rule->setValue(1);
                        $isValid = true;
                    }
                    elseif (preg_match('/^no$|^false$/mi', $form_results['value']))
                    {
                        $rule->setValue(0);
                        $isValid = true;
                    }
                    else
                    {
                        $this->addFlash('error', 'Value has to be either 0 or 1, true or false, yes or no.');
                    }
                }
                elseif ($rule->getAttribute() == 'price')
                {
                    $rule->setValue($form_results['value']);
                    $isValid = true;
                }
                elseif($rule->getAttribute() == 'tax')
                {
                    if(preg_match('/^(?\'operand\'[\+\-])\s*(?\'value\'\d*)$/m', $form_results['value']))
                    {
                        $rule->setValue($form_results['value']);
                        $isValid = true;
                    }
                    else
                    {
                        $this->addFlash('error', 'Value has to be +/- ##.  For example, +10, -10');
                    }
                }

                if ($isValid)
                {
                    $this->addFlash('success', 'Rule added successfully!');
                    $em->persist($rule);
                    $em->flush();
                }
            }
        }

        $rules = $em->getRepository('AppBundle:RuleEntity', 'default')->findAllSortedBySort($ruleType);

        // Create built in rules
        $builtIn = array();
        $rule = (new RuleEntity())
            ->setSort('0')
            ->setTargetName('Anything Refinable')
            ->setTarget('Global Rule')
            ->setAttribute('Is Refined')
            ->setValue($this->get("helper")->getSetting("value_minerals", $ruleType) == 1 ? 'Yes' : 'No');
        $builtIn[] = $rule;

        $rule = (new RuleEntity())
            ->setSort('0')
            ->setTargetName('Anything Salvageable')
            ->setTarget('Global Rule')
            ->setAttribute('Is Refined')
            ->setValue($this->get("helper")->getSetting("value_salvage", $ruleType) == 1 ? 'Yes' : 'No');
        $builtIn[] = $rule;

        $rule = (new RuleEntity())
            ->setSort('0')
            ->setTargetName('Any')
            ->setTarget('Global Rule')
            ->setAttribute('Can Buy')
            ->setValue($this->get('helper')->getSetting('default_buyaction_deny', $ruleType) == 1 ? 'No' : 'Yes');
        $builtIn[] = $rule;


        return $this->render('rules/index.html.twig', array(
            'page_name' => 'Item Rules',
            'sub_text' => '',
            'groupform' => $groupForm->createView(),
            'typeform' => $typeForm->createView(),
            'rules' => $rules,
            'builtin' => $builtIn,
            'testform' => $testForm->createView(),
            'marketgroupform' => $marketGroupForm->createView(),
            'results' => $results,
            'roleform' => $roleForm->createView(),
            'ruleType' => $ruleType));
    }

    /**
     * @Route("/system/admin/settings/rules/delete/{id}", name="admin_buyback_rules_delete")
     */
    public function deleteAction(Request $request, $id)
    {
        $rule = $this->getDoctrine()->getRepository('AppBundle:RuleEntity', 'default')->findOneById($id);

        if($rule != null)
        {
            $em = $this->getDoctrine()->getManager();

            $rules = $this->getDoctrine()->getRepository('AppBundle:RuleEntity', 'default')->findAllAfter($rule->getSort(), $rule->getRuleType());

            $em->remove($rule);

            foreach($rules as $rule)
            {
                $rule->setSort($rule->getSort() - 1);
            }

            $em->flush();

            $this->addFlash('success', "Deleted rule");
        }
        
        return $this->redirectToRoute('admin_buyback_rules');
    }

    /**
     * @Route("/system/admin/settings/rules/up/{id}", name="admin_buyback_rules_up")
     */
    public function upAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();

        $rule = $this->getDoctrine()->getRepository('AppBundle:RuleEntity', 'default')->findOneById($id);

        if($rule != null)
        {
            $prevRule = $this->getDoctrine()->getRepository('AppBundle:RuleEntity', 'default')->findOneBySort($rule->getSort() - 1, array('sort' => 'ASC'));

            if($prevRule != null) {
                $prevRule->setSort($rule->getSort());
                $rule->setSort($rule->getSort() - 1);

                $em->flush();

                $this->addFlash('success', "Moved rule");
            }
        }

        return $this->redirectToRoute('admin_buyback_rules');
    }

    /**
     * @Route("/system/admin/settings/rules/down/{id}", name="admin_buyback_rules_down")
     */
    public function downAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();

        $rule = $this->getDoctrine()->getRepository('AppBundle:RuleEntity', 'default')->findOneById($id);

        if($rule != null)
        {
            $nextRule = $this->getDoctrine()->getRepository('AppBundle:RuleEntity', 'default')->findOneBySort($rule->getSort() + 1, array('sort' => 'ASC'));

            if($nextRule != null) {
                $nextRule->setSort($rule->getSort());
                $rule->setSort($rule->getSort() + 1);

                $em->flush();

                $this->addFlash('success', "Moved rule");
            }
        }

        return $this->redirectToRoute('admin_buyback_rules');
    }
}
