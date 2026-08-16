const SupportCenterComponent = () => import("../../components/admin/support/SupportCenterComponent.vue");

export default [
    {
        path: "/admin/support",
        component: SupportCenterComponent,
        name: "admin.support",
        meta: {
            isFrontend: false,
            auth: true,
            permissionUrl: "support",
            breadcrumb: "support_center",
        },
    },
];
