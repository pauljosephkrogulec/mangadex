import { describe, it, expect, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import ReaderControls from "../ReaderControls";

describe("ReaderControls", () => {
  const baseProps = {
    currentPage: 3,
    totalPages: 10,
    onPrevPage: vi.fn(),
    onNextPage: vi.fn(),
    onPrevChapter: null,
    onNextChapter: null,
  };

  it("renders page indicator", () => {
    render(<ReaderControls {...baseProps} />);
    expect(screen.getByText("3 / 10")).toBeInTheDocument();
  });

  it("renders prev and next page buttons", () => {
    render(<ReaderControls {...baseProps} />);
    expect(screen.getByLabelText("Previous page")).toBeInTheDocument();
    expect(screen.getByLabelText("Next page")).toBeInTheDocument();
  });

  it("calls onPrevPage when prev button clicked", async () => {
    const user = userEvent.setup();
    const onPrevPage = vi.fn();
    render(<ReaderControls {...baseProps} onPrevPage={onPrevPage} />);

    await user.click(screen.getByLabelText("Previous page"));
    expect(onPrevPage).toHaveBeenCalledOnce();
  });

  it("calls onNextPage when next button clicked", async () => {
    const user = userEvent.setup();
    const onNextPage = vi.fn();
    render(<ReaderControls {...baseProps} onNextPage={onNextPage} />);

    await user.click(screen.getByLabelText("Next page"));
    expect(onNextPage).toHaveBeenCalledOnce();
  });

  it("disables prev page button on first page", () => {
    render(<ReaderControls {...baseProps} currentPage={1} />);
    expect(screen.getByLabelText("Previous page")).toBeDisabled();
  });

  it("disables next page button on last page", () => {
    render(<ReaderControls {...baseProps} currentPage={10} />);
    expect(screen.getByLabelText("Next page")).toBeDisabled();
  });

  it("enables prev page button on non-first page", () => {
    render(<ReaderControls {...baseProps} currentPage={2} />);
    expect(screen.getByLabelText("Previous page")).toBeEnabled();
  });

  it("enables next page button on non-last page", () => {
    render(<ReaderControls {...baseProps} currentPage={9} />);
    expect(screen.getByLabelText("Next page")).toBeEnabled();
  });

  it("shows prev chapter button when onPrevChapter provided", () => {
    render(
      <ReaderControls
        {...baseProps}
        onPrevChapter={vi.fn()}
      />,
    );
    expect(screen.getByLabelText("Previous chapter")).toBeInTheDocument();
  });

  it("shows next chapter button when onNextChapter provided", () => {
    render(
      <ReaderControls
        {...baseProps}
        onNextChapter={vi.fn()}
      />,
    );
    expect(screen.getByLabelText("Next chapter")).toBeInTheDocument();
  });

  it("calls onPrevChapter when clicked", async () => {
    const user = userEvent.setup();
    const onPrevChapter = vi.fn();
    render(
      <ReaderControls
        {...baseProps}
        onPrevChapter={onPrevChapter}
      />,
    );

    await user.click(screen.getByLabelText("Previous chapter"));
    expect(onPrevChapter).toHaveBeenCalledOnce();
  });

  it("calls onNextChapter when clicked", async () => {
    const user = userEvent.setup();
    const onNextChapter = vi.fn();
    render(
      <ReaderControls
        {...baseProps}
        onNextChapter={onNextChapter}
      />,
    );

    await user.click(screen.getByLabelText("Next chapter"));
    expect(onNextChapter).toHaveBeenCalledOnce();
  });

  it("renders chapter number and title when provided", () => {
    render(
      <ReaderControls
        {...baseProps}
        chapterNumber="5"
        chapterTitle="The Battle Begins"
      />,
    );
    expect(screen.getByText(/Ch\. 5 - The Battle Begins/)).toBeInTheDocument();
  });

  it("renders chapter number without title", () => {
    render(
      <ReaderControls
        {...baseProps}
        chapterNumber="5"
      />,
    );
    expect(screen.getByText(/Ch\. 5$/)).toBeInTheDocument();
  });

  it("hides prev chapter button when onPrevChapter is null", () => {
    render(<ReaderControls {...baseProps} />);
    expect(screen.queryByLabelText("Previous chapter")).not.toBeInTheDocument();
  });

  it("hides next chapter button when onNextChapter is null", () => {
    render(<ReaderControls {...baseProps} />);
    expect(screen.queryByLabelText("Next chapter")).not.toBeInTheDocument();
  });
});
