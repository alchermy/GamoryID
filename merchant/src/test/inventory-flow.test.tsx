// @vitest-environment jsdom
import '@testing-library/jest-dom/vitest'
import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it } from 'vitest'
import App from '../App'

afterEach(cleanup)

describe('inventory flow', () => {
  it('ค้นหา exact tag และบันทึกขายได้', async () => {
    const user = userEvent.setup()
    render(<App />)

    const search = screen.getByRole('textbox', { name: 'ค้นหาไอดี' })
    await user.type(search, '#Q7N2P')
    expect(screen.getAllByText('Champions 2023 · Vandal').length).toBeGreaterThan(0)
    expect(screen.queryByText('Prime 2.0 · Phantom')).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'ขาย #Q7N2P' }))
    await user.type(screen.getByLabelText('ชื่อลูกค้า'), 'ลูกค้าทดสอบ')
    await user.click(screen.getByRole('button', { name: 'ยืนยันการขาย' }))
    expect(await screen.findByText('บันทึกขาย #Q7N2P สำเร็จ')).toBeInTheDocument()
  })

  it('เปิดฟอร์มเพิ่มไอดีและแสดงข้อมูลใหม่ในคลัง', async () => {
    const user = userEvent.setup()
    render(<App />)
    await user.click(screen.getByRole('button', { name: 'เพิ่มไอดี' }))
    await user.type(screen.getByLabelText('ชื่อรายการ'), 'รายการใหม่สำหรับทดสอบ')
    await user.type(screen.getByLabelText('ต้นทุน'), '1000')
    await user.type(screen.getByLabelText('ราคาตั้งขาย'), '1500')
    await user.click(screen.getByRole('button', { name: 'เพิ่มเข้าคลัง' }))
    expect((await screen.findAllByText('รายการใหม่สำหรับทดสอบ')).length).toBeGreaterThan(0)
  })
})
