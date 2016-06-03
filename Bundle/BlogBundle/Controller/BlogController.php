<?php

namespace Victoire\Bundle\BlogBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Victoire\Bundle\BlogBundle\Entity\Blog;
use Victoire\Bundle\BlogBundle\Form\BlogCategoryType;
use Victoire\Bundle\BlogBundle\Form\BlogSettingsType;
use Victoire\Bundle\BlogBundle\Form\BlogType;
use Victoire\Bundle\BlogBundle\Form\ChooseBlogType;
use Victoire\Bundle\BlogBundle\Repository\BlogRepository;
use Victoire\Bundle\BusinessPageBundle\Entity\BusinessTemplate;
use Victoire\Bundle\PageBundle\Controller\BasePageController;
use Victoire\Bundle\PageBundle\Entity\BasePage;
use Victoire\Bundle\ViewReferenceBundle\ViewReference\ViewReference;

/**
 * blog Controller.
 *
 * @Route("/victoire-dcms/blog")
 */
class BlogController extends BasePageController
{
    protected $routes;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->routes = [
            'new'       => 'victoire_blog_new',
            'show'      => 'victoire_core_page_show',
            'settings'  => 'victoire_blog_settings',
            'articles'  => 'victoire_blog_articles',
            'category'  => 'victoire_blog_category',
            'delete'    => 'victoire_blog_delete',
        ];
    }

    /**
     * New page.
     *
     * @Route("/index/{blogId}/{tab}", name="victoire_blog_index", defaults={"blogId" = null, "tab" = "articles"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function indexAction(Request $request, $blogId = null, $tab = 'articles')
    {
        /** @var BlogRepository $blogRepo */
        $blogRepo = $this->get('doctrine.orm.entity_manager')->getRepository('VictoireBlogBundle:Blog');
        $blogs = $blogRepo->getAll()->run();
        $blog = reset($blogs);
        if (is_numeric($blogId)) {
            $blog = $blogRepo->find($blogId);
        }
        $options['blog'] = $blog;
        $template = $this->getBaseTemplatePath().':index.html.twig';
        $chooseBlogForm = $this->createForm(ChooseBlogType::class, null, $options);

        $chooseBlogForm->handleRequest($request);
        if ($chooseBlogForm->isValid()) {
            $blog = $chooseBlogForm->getData()['blog'];
            $template = $this->getBaseTemplatePath().':_blogItem.html.twig';
            $chooseBlogForm = $this->createForm(ChooseBlogType::class, null, ['blog' => $blog]);
        }
        $businessProperties = [];

        if ($blog instanceof BusinessTemplate) {
            //we can use the business entity properties on the seo
            $businessEntity = $this->get('victoire_core.helper.business_entity_helper')->findById($blog->getBusinessEntityId());
            $businessProperties = $businessEntity->getBusinessPropertiesByType('seoable');
        }

        return new JsonResponse(
            [
                'html' => $this->container->get('templating')->render(
                    $template,
                    [
                        'blog'               => $blog,
                        'currentTab'         => $tab,
                        'tabs'               => ['articles', 'settings', 'category'],
                        'chooseBlogForm'     => $chooseBlogForm->createView(),
                        'businessProperties' => $businessProperties,
                    ]
                ),
            ]
        );
    }

    /**
     * New page.
     *
     * @Route("/feed/{slug}.rss", name="victoire_blog_rss", defaults={"_format" = "rss"})
     *
     * @param Request $request
     * @Template("VictoireBlogBundle:Blog:feed.rss.twig")
     *
     * @return JsonResponse
     */
    public function feedAction(Request $request, Blog $blog)
    {
        return [
                'blog' => $blog,
            ];
    }

    /**
     * New page.
     *
     * @Route("/new", name="victoire_blog_new")
     * @Template()
     *
     * @return JsonResponse
     */
    public function newAction(Request $request, $isHomepage = false)
    {
        return new JsonResponse(parent::newAction($request));
    }

    /**
     * Blog settings.
     *
     * @param Request  $request
     * @param BasePage $blog
     *
     * @return JsonResponse
     * @Route("/{id}/settings", name="victoire_blog_settings")
     * @ParamConverter("blog", class="VictoirePageBundle:BasePage")
     */
    public function settingsAction(Request $request, BasePage $blog)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $form = $this->createForm($this->getPageSettingsType(), $blog);
        $businessProperties = [];

        $form->handleRequest($request);

        if ($form->isValid()) {
            $entityManager->persist($blog);
            $entityManager->flush();

            /** @var ViewReference $reference */
            $reference = $this->get('victoire_view_reference.repository')
            ->getOneReferenceByParameters(['viewId' => $blog->getId()]);

            return new JsonResponse([
                'success' => true,
                'url'     => $this->generateUrl(
                    'victoire_core_page_show', [
                        '_locale' => $blog->getCurrentLocale(), 'url' => $reference->getUrl(),
                ]),
            ]);
        }
        //we display the form
        $errors = $this->get('victoire_form.error_helper')->getRecursiveReadableErrors($form);
        if ($errors != '') {
            return new JsonResponse(['html' => $this->container->get('templating')->render(
                        $this->getBaseTemplatePath().':Tabs/_settings.html.twig',
                            [
                                'blog'               => $blog,
                                'form'               => $form->createView(),
                                'businessProperties' => $businessProperties,
                            ]
                        ),
                        'message' => $errors,
                    ]
                );
        }

        return new Response($this->container->get('templating')->render(
                    $this->getBaseTemplatePath().':Tabs/_settings.html.twig',
                    [
                        'blog'               => $blog,
                        'form'               => $form->createView(),
                        'businessProperties' => $businessProperties,
                    ]
                )
        );
    }

    /**
     * Blog settings.
     *
     * @param Request  $request
     * @param BasePage $blog
     *
     * @return Response
     * @Route("/{id}/category", name="victoire_blog_category")
     * @ParamConverter("blog", class="VictoirePageBundle:BasePage")
     */
    public function categoryAction(Request $request, BasePage $blog)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $form = $this->createForm($this->getPageCategoryType(), $blog);
        $businessProperties = [];

        //if the page is a business entity page
        if ($blog instanceof BusinessTemplate) {
            //we can use the business entity properties on the seo
            $businessEntity = $this->get('victoire_core.helper.business_entity_helper')->findById($blog->getBusinessEntityId());
            $businessProperties = $businessEntity->getBusinessPropertiesByType('seoable');
        }

        $form->handleRequest($request);

        if ($form->isValid()) {
            $entityManager->persist($blog);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'url'     => $this->generateUrl('victoire_core_page_show', ['_locale' => $blog->getCurrentLocale(), 'url' => $blog->getUrl()]), ]);
        }
        //we display the form
        $errors = $this->get('victoire_form.error_helper')->getRecursiveReadableErrors($form);
        if ($errors != '') {
            return new JsonResponse(['html' => $this->container->get('templating')->render(
                        $this->getBaseTemplatePath().':Tabs/_category.html.twig',
                            [
                                'blog'               => $blog,
                                'form'               => $form->createView(),
                                'businessProperties' => $businessProperties,
                            ]
                        ),
                        'message' => $errors,
                    ]
                );
        }

        return new Response($this->container->get('templating')->render(
                    $this->getBaseTemplatePath().':Tabs/_category.html.twig',
                    [
                        'blog'               => $blog,
                        'form'               => $form->createView(),
                        'businessProperties' => $businessProperties,
                    ]
                )
        );
    }

    /**
     * Blog settings.
     *
     * @param Request  $request
     * @param BasePage $blog
     *
     * @return Response
     * @Route("/{id}/articles", name="victoire_blog_articles")
     * @ParamConverter("blog", class="VictoirePageBundle:BasePage")
     */
    public function articlesAction(Request $request, BasePage $blog)
    {
        return new Response($this->container->get('templating')->render(
                    $this->getBaseTemplatePath().':Tabs/_articles.html.twig',
                    [
                        'blog' => $blog,
                    ]
                )
        );
    }

    /**
     * Page delete.
     *
     * @param Blog $blog
     *
     * @return JsonResponse
     * @Route("/{id}/delete", name="victoire_blog_delete")
     * @Template()
     * @ParamConverter("blog", class="VictoirePageBundle:BasePage")
     */
    public function deleteAction(BasePage $blog)
    {
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_VICTOIRE', $blog)) {
            throw new AccessDeniedException("Nop ! you can't do such an action");
        }

        foreach ($blog->getArticles() as $_article) {
            $bep = $this->get('victoire_page.page_helper')->findPageByParameters(
                [
                    'templateId' => $_article->getTemplate()->getId(),
                    'entityId'   => $_article->getId(),
                ]
            );
            $this->get('victoire_blog.manager.article')->delete($_article, $bep);
        }

        return new JsonResponse(parent::deleteAction($blog));
    }

    /**
     * @return string
     */
    protected function getPageSettingsType()
    {
        return BlogSettingsType::class;
    }

    /**
     * @return string
     */
    protected function getPageCategoryType()
    {
        return BlogCategoryType::class;
    }

    /**
     * @return string
     */
    protected function getNewPageType()
    {
        return BlogType::class;
    }

    /**
     * @return \Victoire\Bundle\BlogBundle\Entity\Blog
     */
    protected function getNewPage()
    {
        return new Blog();
    }

    /**
     * @return string
     */
    protected function getBaseTemplatePath()
    {
        return 'VictoireBlogBundle:Blog';
    }

    /**
     * @param unknown $action
     */
    protected function getRoutes($action)
    {
        return $this->routes[$action];
    }
}
