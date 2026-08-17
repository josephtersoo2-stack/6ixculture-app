const SupportCenterComponent = () => import("../../components/admin/support/SupportCenterComponent.vue");
const SupportGovernance = () => import("../../components/admin/support/governance/SupportGovernance.vue");

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
    {
        path: "/admin/support/governance",
        component: SupportGovernance,
        name: "admin.support.governance",
        meta: {
            isFrontend: false,
            auth: true,
            permissionUrl: "support_governance",
            breadcrumb: "support_governance",
        },
    },
];
