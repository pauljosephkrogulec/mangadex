import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  images: {
    remotePatterns: [
      { protocol: 'https', hostname: 'picsum.photos' },
      { protocol: 'http', hostname: 'localhost' },
      { protocol: 'https', hostname: 'localhost' },
    ],
  },
  async rewrites() {
    return [
      {
        source: "/api/:path*",
        destination: "http://nginx:80/api/:path*",
      },
    ];
  },
};

export default nextConfig;
