// @vitest-environment jsdom
import "@testing-library/jest-dom/vitest";
import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { TeamPanel } from "../features/team/team-components";
import type { TeamMember } from "../types/models";

afterEach(cleanup);

describe("Team Discord permissions", () => {
  it("อธิบายว่าสิทธิ์หน้าเว็บใช้ควบคุมคำสั่ง Discord และส่งค่าที่แก้ไขให้ยืนยัน", async () => {
    const user = userEvent.setup();
    const onPermissionsChange = vi.fn();
    const member: TeamMember = {
      id: 7,
      role: "staff",
      permissions: ["inventory.sell"],
      user: {
        id: 9,
        name: "พนักงานขาย",
        email: "staff@example.test",
      },
    };

    render(
      <TeamPanel
        members={[member]}
        invitations={[]}
        loading={false}
        error=""
        canManage={true}
        invite={() => undefined}
        onPermissionsChange={onPermissionsChange}
        onRemove={() => undefined}
        onRevokeInvitation={() => undefined}
        retry={() => undefined}
      />,
    );

    expect(screen.getByText("สิทธิ์คำสั่ง Discord ของพนักงาน")).toBeVisible();
    expect(screen.getByText(/บอทจะตรวจสิทธิ์ล่าสุดทุกครั้ง/)).toBeVisible();

    expect(screen.getAllByText("พนักงานขาย")).toHaveLength(2);
    await user.click(screen.getAllByLabelText("จัดการสต็อก")[0]);
    expect(onPermissionsChange).toHaveBeenCalledWith(member, [
      "inventory.sell",
      "inventory.manage",
    ]);
  });
});
