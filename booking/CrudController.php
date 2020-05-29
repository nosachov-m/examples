<?php

namespace UserBundle\Controller;

use CatalogBundle\Document\Activity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;
use UserBundle\Document\Customer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use CoreBundle\Controller\CoreController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormBuilder;
use UserBundle\Document\Employee;

class CrudController extends CoreController
{
    /**
     * Get users list (ROLE_ADMIN, ROLE_INSTRUCTOR).
     *
     * Access URI /employees/{page}/{onPage}
     *
     * @Security("has_role('ROLE_INSTRUCTOR')")
     *
     * @param int     $page
     * @param int     $onPage
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function employeesAction($page, $onPage, Request $request)
    {
        $search = $request->query->get('s');

        $employees = $this->get('knp_paginator')->paginate(
            $this->get('booking.user.employee.repository')->searchQuery($search),
            $page,
            $onPage ?: $this->getParameter('on_page')
        );

        return $this->render(
            '@User/Crud/employee_list.html.twig',
            [
                'employees' => $employees,
            ]
        );
    }
    /**
 * Get users list (ROLE_ADMIN, ROLE_INSTRUCTOR).
 *
 * Access URI /employees/aktiv/{page}/{onPage}
 *
 * @Security("has_role('ROLE_INSTRUCTOR')")
 *
 * @param int     $page
 * @param int     $onPage
 * @param Request $request
 *
 * @return \Symfony\Component\HttpFoundation\Response
 */
    public function employeesAktivAction($page, $onPage, Request $request)
    {
        $search = $request->query->get('s');

        $employees = $this->get('knp_paginator')->paginate(
            $this->get('booking.user.employee.repository')->searchQueryAktiv($search),
            $page,
            $onPage ?: $this->getParameter('on_page')
        );

        return $this->render(
            '@User/Crud/employee_list.html.twig',
            [
                'employees' => $employees,
            ]
        );
    }
    /**
     * Get users list (ROLE_ADMIN, ROLE_INSTRUCTOR).
     *
     * Access URI /employees/inaktiv/{page}/{onPage}
     *
     * @Security("has_role('ROLE_INSTRUCTOR')")
     *
     * @param int     $page
     * @param int     $onPage
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function employeesInAktivAction($page, $onPage, Request $request)
    {
        $search = $request->query->get('s');

        $employees = $this->get('knp_paginator')->paginate(
            $this->get('booking.user.employee.repository')->searchQueryInAktiv($search),
            $page,
            $onPage ?: $this->getParameter('on_page')
        );

        return $this->render(
            '@User/Crud/employee_list.html.twig',
            [
                'employees' => $employees,
            ]
        );
    }

    /**
     * Get ajax employees list (ROLE_ADMIN).
     *
     * Access URI /employees/ajax/{activityId}
     *
     * @Security("has_role('ROLE_ADMIN')")
     *
     * @param Activity $activity
     * @ParamConverter("activity", class="CatalogBundle:Activity", options={"id" = "activityId"})
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function ajaxEmployeesAction(Activity $activity = null)
    {
        return $this->jsonResponse(
            $this->get('serializer')->serialize(
                array_values(
                    $this->get('booking.user.employee.repository')->getAllInstructors($activity)->getQuery()->toArray()
                ),
                'json'
            )
        );
    }

    /**
     * Get user (ROLE_ADMIN, ROLE_INSTRUCTOR).
     *
     * Access URI /employees/{id}
     *
     * @Security("has_role('ROLE_INSTRUCTOR')")
     *
     * @param Employee $employee
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function employeeShowAction(Employee $employee)
    {
        return $this->render('@User/Crud/employee_show.html.twig', ['employee' => $employee]);
    }

    /**
     * Create user (ROLE_ADMIN, ROLE_INSTRUCTOR).
     *
     * Access URI /employees/new
     *
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function employeeNewAction(Request $request)
    {
        $employee = new Employee();

        $form = $this->createForm('UserBundle\Form\Type\EmployeeType', $employee)
            ->add('saveAndCreateNew', 'Symfony\Component\Form\Extension\Core\Type\SubmitType');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $documentManager = $this->getDm();
            $documentManager->persist($employee);
            $documentManager->flush();

            $this->addFlash('success', $this->get('translator')->trans('employee.created_successfully', [], 'UserBundle'));
            if ($form->get('saveAndCreateNew')->isClicked()) {
                return $this->redirectToRoute('_booking_employees_new');
            }

            return $this->redirectToRoute('_booking_employees_list');
        }

        return $this->render(
            '@User/Crud/employee_new.html.twig',
            array(
                'employee' => $employee,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Edit user (ROLE_ADMIN, ROLE_INSTRUCTOR).
     *
     * Access URI /employees/{id}/edit
     *
     * @Security("has_role('ROLE_INSTRUCTOR')")
     *
     * @param Employee $employee
     * @param Request  $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function employeeEditAction(Employee $employee, Request $request)
    {
        $user = clone $employee;
        $this->denyAccessUnlessGranted('edit', $employee);

        $editForm = $this->createForm('UserBundle\Form\Type\EmployeeType', $employee)->remove('plainPassword');

        if (!$this->get('security.authorization_checker')->isGranted('ROLE_SUPER_ADMIN')) {
            $editForm->remove('enabled')->remove('roles');
        }

        $editForm->handleRequest($request);
        if ($editForm->isValid()) {
            $bookings = $this->get('booking.user.user_manager')->checkBookingsByDate($employee, $user);
            if (0 < count($bookings)) {
                return $this->render(
                    '@User/Crud/user_edit_error.html.twig',
                    [
                        'bookings' => $bookings,
                    ]
                );
            }

            $documentManager = $this->getDm();
            $documentManager->flush();
            $this->addFlash('success', $this->get('translator')->trans('employee.edited_successfully', [], 'UserBundle'));

            return $this->redirectToRoute('_booking_employees_list');
        }

        return $this->render(
            '@User/Crud/employee_edit.html.twig',
            array(
                'employee' => $employee,
                'edit_form' => $editForm->createView(),
                'delete_form' => $this->get('security.authorization_checker')->isGranted(
                    'ROLE_SUPER_ADMIN'
                ) ? $this->createDeleteForm(
                    $employee,
                    '_booking_employees_delete'
                )->createView() : false,
            )
        );
    }

    /**
     * Delete user (ROLE_ADMIN, ROLE_INSTRUCTOR).
     *
     * Access URI /employees/{id}/delete
     *
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     *
     * @param Employee $employee
     * @param Request  $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function employeeDeleteAction(Request $request, Employee $employee)
    {
        $form = $this->createDeleteForm($employee, '_booking_customers_delete');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $bookings = $this->get('booking.user.user_manager')->checkBookingsBeforeDeleteUser($employee);
            if (0 < count($bookings)) {
                return $this->render(
                    '@User/Crud/user_edit_error.html.twig',
                    [
                        'bookings' => $bookings,
                    ]
                );
            }

            $entityManager = $this->getDm();
            $entityManager->remove($employee);
            $entityManager->flush();
            $this->addFlash('success', $this->get('translator')->trans('employee.deleted_successfully', [], 'UserBundle'));
        }

        return $this->redirectToRoute('_booking_employees_list');
    }

    /**
     * Create delete form builder.
     *
     * @param Employee|Customer $user
     * @param string            $url
     *
     * @return FormBuilder
     */
    private function createDeleteForm($user, $url)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl($url, array('id' => $user->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    /**
     * Get users list (ROLE_CUSTOMER).
     *
     * Access URI /customers/{page}/{onPage}
     *
     * @Security("has_role('ROLE_INSTRUCTOR')")
     *
     * @param string     $filter
     * @param int     $page
     * @param int     $onPage
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function customersAction($page, $onPage, Request $request)
    {
        $search = $request->query->get('s');
        $customers = $this->get('knp_paginator')->paginate(
            $this->get('booking.user.customer.repository')->searchQuery($search),
            $page,
            $onPage ?: $this->getParameter('on_page')
        );

        return $this->render(
            '@User/Crud/customer_list.html.twig',
            [
                'customers' => $customers,
            ]
        );
    }
    /**
     * Get users list (ROLE_CUSTOMER).
     *
     * Access URI /customers/aktiv/{page}/{onPage}
     *
     * @Security("has_role('ROLE_INSTRUCTOR')")
     *
     * @param string     $filter
     * @param int     $page
     * @param int     $onPage
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function customersAktivAction($page, $onPage, Request $request)
    {
        $search = $request->query->get('s');
        $customers = $this->get('knp_paginator')->paginate(
            $this->get('booking.user.customer.repository')->getActive(),
            $page,
            $onPage ?: $this->getParameter('on_page')
        );

        return $this->render(
            '@User/Crud/customer_list.html.twig',
            [
                'customers' => $customers,
            ]
        );
    }
    /**
     * Get users list (ROLE_CUSTOMER).
     *
     * Access URI /customers/inaktiv/{page}/{onPage}
     *
     * @Security("has_role('ROLE_INSTRUCTOR')")
     *
     * @param string     $filter
     * @param int     $page
     * @param int     $onPage
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function customersInAktivAction($page, $onPage, Request $request)
    {
        $search = $request->query->get('s');
        $customers = $this->get('knp_paginator')->paginate(
            $this->get('booking.user.customer.repository')->getInActive(),
            $page,
            $onPage ?: $this->getParameter('on_page')
        );

        return $this->render(
            '@User/Crud/customer_list.html.twig',
            [
                'customers' => $customers,
            ]
        );
    }

    /**
     * Get ajax customers list (ROLE_ADMIN).
     *
     * Access URI /customers/ajax
     *
     * @Security("has_role('ROLE_ADMIN')")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function ajaxCustomersAction()
    {
        return $this->jsonResponse(
            $this->get('serializer')->serialize(
                array_values(
                    $this->get('booking.user.customer.repository')->findActive()->getQuery()->toArray()
                ),
                'json'
            )
        );
    }

    /**
     * Get user (ROLE_CUSTOMER).
     *
     * Access URI /customers/{id}
     *
     * @Security("has_role('ROLE_INSTRUCTOR')")
     *
     * @param Customer $customer
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function customerShowAction(Customer $customer)
    {
        return $this->render('@User/Crud/customer_show.html.twig', ['customer' => $customer]);
    }

    /**
     * Create user (ROLE_CUSTOMER).
     *
     * Access URI /customers/new
     *
     * @Security("has_role('ROLE_ADMIN')")
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function customerNewAction(Request $request)
    {
        $customer = new Customer();
        $customer->setEnabled(true);

        $form = $this->createForm('UserBundle\Form\Type\CustomerType', $customer)
            ->add('saveAndCreateNew', 'Symfony\Component\Form\Extension\Core\Type\SubmitType');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $documentManager = $this->getDm();
            $documentManager->persist($customer);
            $documentManager->flush();

            $this->addFlash('success', $this->get('translator')->trans('customer.created_successfully', [], 'UserBundle'));
            if ($form->get('saveAndCreateNew')->isClicked()) {
                return $this->redirectToRoute('_booking_customers_new');
            }

            return $this->redirectToRoute('_booking_customers_list');
        }

        return $this->render(
            '@User/Crud/customer_new.html.twig',
            array(
                'customer' => $customer,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Ajax create customer.
     *
     * Access URI /customers/new/ajax
     *
     * @Security("has_role('ROLE_ADMIN')")
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function ajaxCustomerNewAction(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->jsonResponse(
                $this->get('serializer')->serialize(
                    [
                        'message' => $this->get('translator')->trans('ajax_access', [], 'BookingBundle'),
                    ],
                    'json'
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        $customer = new Customer();
        $form = $this->createForm('UserBundle\Form\Type\CustomerType', $customer);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $documentManager = $this->getDm();
            $documentManager->persist($customer);
            $documentManager->flush();

            return $this->jsonResponse(
                $this->get('serializer')->serialize(
                    [
                        'message' => 'Success',
                        'id' => $customer->getId(),
                    ],
                    'json'
                ),
                Response::HTTP_OK
            );
        }

        return $this->jsonResponse(
            $this->get('serializer')->serialize(
                [
                    'errors' => $this->addSlashedErrors($this->get('validator')->validate($customer)),
                ],
                'json'
            ),
            Response::HTTP_BAD_REQUEST
        );
    }

    /**
     * Edit user (ROLE_CUSTOMER).
     *
     * Access URI /customers/{id}/edit
     *
     * @Security("has_role('ROLE_ADMIN')")
     *
     * @param Customer $customer
     * @param Request  $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function customerEditAction(Customer $customer, Request $request)
    {
        $user = clone $customer;

        $editForm = $this->createForm('UserBundle\Form\Type\CustomerType', $customer);

        $editForm->handleRequest($request);


        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $bookings = $this->get('booking.user.user_manager')->checkBookings($customer, $user);
            if (0 < count($bookings)) {
                return $this->render(
                    '@User/Crud/user_edit_error.html.twig',
                    [
                        'bookings' => $bookings,
                    ]
                );
            }

            $documentManager = $this->getDm();
            $documentManager->flush();


            $this->addFlash('success', $this->get('translator')->trans('customer.edited_successfully', [], 'UserBundle'));


                return $this->redirectToRoute('_booking_customers_list');


        }


            return $this->render(
                '@User/Crud/customer_edit.html.twig',
                array(
                    'customer' => $customer,
                    'edit_form' => $editForm->createView(),
                    'delete_form' => $this->createDeleteForm($customer, '_booking_customers_delete')->createView(),
                )
            );

    }

    /**
     * Delete user (ROLE_CUSTOMER).
     *
     * Access URI /customers/{id}/delete
     *
     * @Security("has_role('ROLE_ADMIN')")
     *
     * @param Customer $customer
     * @param Request  $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function customerDeleteAction(Request $request, Customer $customer)
    {
        $form = $this->createDeleteForm($customer, '_booking_customers_delete');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $bookings = $this->get('booking.user.user_manager')->checkBookingsBeforeDeleteUser($customer);
            if (0 < count($bookings)) {
                return $this->render(
                    '@User/Crud/user_edit_error.html.twig',
                    [
                        'bookings' => $bookings,
                    ]
                );
            }
            $entityManager = $this->getDm();
            $entityManager->remove($customer);
            $entityManager->flush();
            $this->addFlash('success', $this->get('translator')->trans('customer.deleted_successfully', [], 'UserBundle'));
        }

        return $this->redirectToRoute('_booking_customers_list');
    }

    /**
     * Edit user super admin.
     *
     * Access URI /profile/edit
     *
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function superAdminEditAction(Request $request)
    {
        $superAdmin = $this->get('booking.user.super_admin.repository')->getSuperAdmin();

        $editForm = $this->createForm(
            'UserBundle\Form\Type\SuperAdminType',
            $superAdmin
        );

        $editForm->handleRequest($request);
        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $documentManager = $this->getDm();
            $documentManager->flush();
            $this->addFlash('success', $this->get('translator')->trans('super_admin.edited_successfully', [], 'UserBundle'));

            return $this->redirect('/');
        }

        return $this->render(
            '@User/Crud/super_admin_edit.html.twig',
            [
                'super_admin' => $superAdmin,
                'edit_form' => $editForm->createView(),
            ]
        );
    }
}