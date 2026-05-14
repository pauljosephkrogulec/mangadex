import { describe, it, expect, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import ReaderSidebar from "../ReaderSidebar";

describe("ReaderSidebar", () => {
  const baseProps = {
    open: true,
    pinned: false,
    onClose: vi.fn(),
    onPinToggle: vi.fn(),
    onPageChange: vi.fn(),
    onChapterChange: vi.fn(),
    chapters: [
      { id: "ch-1", chapterNumber: "1", title: null, volume: "1" },
      { id: "ch-2", chapterNumber: "2", title: "Revelation", volume: "1" },
    ] as any[],
    currentChapterId: "ch-1",
    currentPage: 3,
    totalPages: 10,
  };

  it("renders page indicator in progress bar", () => {
    render(<ReaderSidebar {...baseProps} />);
    expect(screen.getByTestId("progress-current-page")).toHaveTextContent("3");
  });

  it("does not render content when closed", () => {
    render(<ReaderSidebar {...baseProps} open={false} />);
    expect(screen.queryByText("Menu")).not.toBeInTheDocument();
  });

  it("renders chapter number and title in header", () => {
    render(
      <ReaderSidebar
        {...baseProps}
        chapterNumber="5"
        chapterTitle="The Battle"
      />,
    );
    expect(screen.getByText("Ch. 5 - The Battle")).toBeInTheDocument();
  });

  it("renders language and group in subheader", () => {
    render(
      <ReaderSidebar
        {...baseProps}
        language="en"
        scanlationGroup="MangaPlus"
      />,
    );
    expect(screen.getByText("en")).toBeInTheDocument();
    expect(screen.getByText("MangaPlus")).toBeInTheDocument();
  });

  it("calls onClose when close button clicked", async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();
    render(<ReaderSidebar {...baseProps} onClose={onClose} />);

    await user.click(screen.getByLabelText("Close sidebar"));
    expect(onClose).toHaveBeenCalledOnce();
  });

  it("calls onPinToggle when pin button clicked", async () => {
    const user = userEvent.setup();
    const onPinToggle = vi.fn();
    render(<ReaderSidebar {...baseProps} onPinToggle={onPinToggle} />);

    await user.click(screen.getByLabelText("Pin sidebar"));
    expect(onPinToggle).toHaveBeenCalledOnce();
  });

  it("shows unpin label when pinned", () => {
    render(<ReaderSidebar {...baseProps} pinned={true} />);
    expect(screen.getByLabelText("Unpin sidebar")).toBeInTheDocument();
  });

  it("renders chapter selector when multiple chapters", () => {
    render(<ReaderSidebar {...baseProps} />);
    expect(screen.getByLabelText("Chapter")).toBeInTheDocument();
  });

  it("renders page selector", () => {
    render(<ReaderSidebar {...baseProps} />);
    expect(screen.getByLabelText("Page")).toBeInTheDocument();
  });

  it("renders quick settings section", () => {
    render(<ReaderSidebar {...baseProps} />);
    expect(screen.getByText("Quick Settings")).toBeInTheDocument();
    expect(screen.getByText("Image Fit")).toBeInTheDocument();
    expect(screen.getByText("Show Header")).toBeInTheDocument();
  });

  it("renders fit mode buttons", () => {
    render(<ReaderSidebar {...baseProps} />);
    expect(screen.getByText("Fit Width")).toBeInTheDocument();
    expect(screen.getByText("Fit Height")).toBeInTheDocument();
    expect(screen.getByText("Fit Both")).toBeInTheDocument();
  });

  it("renders Reader Settings link", () => {
    render(<ReaderSidebar {...baseProps} />);
    expect(screen.getByText("Reader Settings")).toBeInTheDocument();
  });

  it("calls onChapterChange when chapter selected", async () => {
    const user = userEvent.setup();
    const onChapterChange = vi.fn();
    render(
      <ReaderSidebar
        {...baseProps}
        onChapterChange={onChapterChange}
      />,
    );

    await user.selectOptions(screen.getByLabelText("Chapter"), "ch-2");
    expect(onChapterChange).toHaveBeenCalledWith("ch-2");
  });

  it("calls onPageChange when page selected", async () => {
    const user = userEvent.setup();
    const onPageChange = vi.fn();
    render(
      <ReaderSidebar
        {...baseProps}
        onPageChange={onPageChange}
        totalPages={10}
      />,
    );

    await user.selectOptions(screen.getByLabelText("Page"), "5");
    expect(onPageChange).toHaveBeenCalledWith(5);
  });

  it("toggles fit mode on button click", async () => {
    const user = userEvent.setup();
    render(<ReaderSidebar {...baseProps} />);

    const fitBoth = screen.getByText("Fit Both");
    await user.click(fitBoth);
    expect(fitBoth.closest("button")).toHaveClass("bg-white/[0.1]");
  });

  it("toggles show header switch", async () => {
    const user = userEvent.setup();
    render(<ReaderSidebar {...baseProps} />);

    const toggle = screen.getByRole("switch", { name: "Show Header" });
    await user.click(toggle);
    expect(toggle).toHaveAttribute("aria-checked", "true");
  });

  it("calls onPageChange when progress bar clicked", async () => {
    const user = userEvent.setup();
    const onPageChange = vi.fn();
    const { container } = render(
      <ReaderSidebar
        {...baseProps}
        onPageChange={onPageChange}
      />,
    );

    const progressBar = container.querySelector(".cursor-pointer.group");
    expect(progressBar).toBeInTheDocument();
    await user.click(progressBar!);
    expect(onPageChange).toHaveBeenCalled();
  });
});
