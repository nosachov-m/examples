<?php

namespace NewsBundle\Controller;

use NewsBundle\Document\News;
use CoreBundle\Controller\CoreController;
use NewsBundle\Security\NewsVoter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\HttpFoundation\Request;

class NewsController extends CoreController
{
    /**
     * Create news (ROLE_ADMIN).
     *
     * Access URI /news/new
     *
     * @Security("has_role('ROLE_ADMIN')")
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function newsNewAction(Request $request)
    {
        $news = new News();
        $form = $this->createForm('NewsBundle\Form\Type\NewsType', $news)
            ->add('saveAndCreateNew', 'Symfony\Component\Form\Extension\Core\Type\SubmitType');
        $form->handleRequest($request);

        if ($form->isValid()) {
            $documentManager = $this->getDm();
            $news = $this->get('booking.news.news_service')->createAuthor($news, $this->getUser());
            $documentManager->persist($news);
            $documentManager->flush();

            $this->addFlash('success', $this->get('translator')->trans('created_successfully', [], 'NewsBundle'));
            if ($form->get('saveAndCreateNew')->isClicked()) {
                return $this->redirectToRoute('_booking_news_create');
            }

            return $this->redirectToRoute('_booking_news_list');
        }

        return $this->render(
            'NewsBundle:News:new.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * Show news (ROLE_ADMIN).
     *
     * Access URI /news/{id}
     *
     * @Security("has_role('ROLE_ADMIN')")
     *
     * @param News $news
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function newsShowAction(News $news)
    {
        return $this->render('NewsBundle:News:show.html.twig', ['news' => $news]);
    }

    /**
     * List of news & announcements.
     *
     * Access URI /news/{page}/{onPage}
     *
     * @Security("is_authenticated()")
     *
     * @param $page
     * @param $onPage
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function newsListAction($page, $onPage, Request $request)
    {
        $search = $request->query->get('s');

        $news = $this->get('knp_paginator')->paginate(
            $this->get('booking.news.news.repository')->searchQuery($search),
            $page,
            $onPage ?: $this->getParameter('on_page')
        );

        return $this->render(
            'NewsBundle:News:list.html.twig',
            [
                'news' => $news,
            ]
        );
    }

    /**
     * Json list of news & announcements.
     *
     * Access URI /news/list_json/{page}/{onPage}
     *
     * @Security("is_authenticated()")
     *
     * @param $page
     * @param $onPage
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function newsListJsonAction($page, $onPage)
    {
        $news = $this->get('knp_paginator')->paginate(
            $this->get('booking.news.news.repository')->getAllQuery(),
            $page,
            $onPage ?: $this->getParameter('on_page')
        );

        return $this->jsonResponse(
            $this->get('serializer')->serialize(
                [
                    'items' => $news->getItems(),
                    'itemsTotal' => $news->getTotalItemCount(),
                    'currentPageNumber' => $news->getCurrentPageNumber(),
                    'numItemsPerPage' => $news->getItemNumberPerPage(),
                ],
                'json'
            )
        );
    }

    /**
     * Edit news (ROLE_ADMIN).
     *
     * Access URI /news/{id}/edit
     *
     * @Security("has_role('ROLE_ADMIN')")
     *
     * @param News    $news
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function newsEditAction(News $news, Request $request)
    {
        $this->denyAccessUnlessGranted(NewsVoter::EDIT, $news);

        $editForm = $this->createForm('NewsBundle\Form\Type\NewsType', $news);
        $editForm->handleRequest($request);
        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $documentManager = $this->getDm();
            $documentManager->flush();
            $this->addFlash('success', $this->get('translator')->trans('edited_successfully', [], 'NewsBundle'));

            return $this->redirectToRoute('_booking_news_list');
        }

        return $this->render(
            'NewsBundle:News:edit.html.twig',
            array(
                'news' => $news,
                'form' => $editForm->createView(),
                'delete_form' => $this->createDeleteForm(
                    $news,
                    '_booking_news_delete'
                )->createView(),
            )
        );
    }

    /**
     * Delete news (ROLE_ADMIN).
     *
     * Access URI /news/{id}/delete
     *
     * @Security("has_role('ROLE_ADMIN')")
     *
     * @param News    $news
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function newsDeleteAction(Request $request, News $news)
    {
        $this->denyAccessUnlessGranted(NewsVoter::EDIT, $news);

        $form = $this->createDeleteForm($news, '_booking_news_delete');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDm();
            $entityManager->remove($news);
            $entityManager->flush();
            $this->addFlash('success', $this->get('translator')->trans('deleted_successfully', [], 'NewsBundle'));
        }

        return $this->redirectToRoute('_booking_news_list');
    }

    /**
     * Create delete form builder.
     *
     * @param News   $news
     * @param string $url
     *
     * @return FormBuilder
     */
    private function createDeleteForm(News $news, $url)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl($url, ['id' => $news->getId()]))
            ->setMethod('DELETE')
            ->getForm();
    }
}
