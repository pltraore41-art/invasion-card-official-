from django.urls import path

from . import views


urlpatterns = [
    path("", views.home, name="home"),
    path("inscriptions/", views.registration_list, name="registration_list"),
    path("inscriptions/nouvelle/", views.registration_create, name="registration_create"),
    path(
        "inscriptions/<str:reference>/ticket.svg",
        views.registration_ticket,
        name="registration_ticket",
    ),
    path("inscriptions/<str:reference>/", views.registration_detail, name="registration_detail"),
    path("participation/", views.participation, name="participation"),
    path("admin/login/", views.OrganizerLoginView.as_view(), name="admin_login"),
    path("admin/logout/", views.OrganizerLogoutView.as_view(), name="admin_logout"),
    path("admin/", views.admin_dashboard, name="admin_dashboard"),
    path("admin/export.csv", views.export_csv, name="export_csv"),
    path("admin/check-in/<str:reference>/", views.admin_check_in, name="admin_check_in"),
    path("admin/<str:reference>/delete/", views.delete_registration, name="delete_registration"),
    path("admin/<str:reference>/", views.admin_registration, name="admin_registration"),
]
