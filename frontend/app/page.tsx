import { Metadata } from 'next'
import { HeroSection } from '@/components/home/HeroSection'
import { FeaturedVehicles } from '@/components/home/FeaturedVehicles'
import { WhyChooseUs } from '@/components/home/WhyChooseUs'
import { HowItWorks } from '@/components/home/HowItWorks'
import { Testimonials } from '@/components/home/Testimonials'
import { CallToAction } from '@/components/home/CallToAction'

export const metadata: Metadata = {
  title: 'Car Rental Platform | Rent Vehicles Easily',
  description:
    'Find and rent vehicles from trusted agencies. Real-time booking, GPS tracking, competitive pricing, and excellent customer service.',
  openGraph: {
    title: 'Car Rental Platform | Rent Vehicles Easily',
    description:
      'Find and rent vehicles from trusted agencies. Real-time booking, GPS tracking, competitive pricing, and excellent customer service.',
    type: 'website',
    url: process.env.NEXT_PUBLIC_APP_URL || 'http://localhost:3000',
    images: [
      {
        url: '/og-home.jpg',
        width: 1200,
        height: 630,
        alt: 'Car Rental Platform Homepage',
      },
    ],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Car Rental Platform | Rent Vehicles Easily',
    description:
      'Find and rent vehicles from trusted agencies. Real-time booking, GPS tracking, competitive pricing.',
    images: ['/og-home.jpg'],
  },
  alternates: {
    canonical: process.env.NEXT_PUBLIC_APP_URL || 'http://localhost:3000',
  },
}

export default function HomePage() {
  return (
    <>
      {/* Hero Section */}
      <HeroSection />

      {/* Featured Vehicles */}
      <section className="py-16 bg-gray-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl font-bold text-gray-900 sm:text-4xl">
              Featured Vehicles
            </h2>
            <p className="mt-4 text-lg text-gray-600 max-w-2xl mx-auto">
              Discover our top-rated vehicles from trusted rental agencies
            </p>
          </div>
          <FeaturedVehicles />
        </div>
      </section>

      {/* Why Choose Us */}
      <section className="py-16 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <WhyChooseUs />
        </div>
      </section>

      {/* How It Works */}
      <section className="py-16 bg-gray-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl font-bold text-gray-900 sm:text-4xl">
              How It Works
            </h2>
            <p className="mt-4 text-lg text-gray-600 max-w-2xl mx-auto">
              Rent a vehicle in just a few simple steps
            </p>
          </div>
          <HowItWorks />
        </div>
      </section>

      {/* Testimonials */}
      <section className="py-16 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl font-bold text-gray-900 sm:text-4xl">
              What Our Customers Say
            </h2>
            <p className="mt-4 text-lg text-gray-600 max-w-2xl mx-auto">
              Real stories from satisfied customers
            </p>
          </div>
          <Testimonials />
        </div>
      </section>

      {/* Call to Action */}
      <CallToAction />

      {/* Structured Data for SEO */}
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{
          __html: JSON.stringify({
            '@context': 'https://schema.org',
            '@type': 'AutoRental',
            name: 'Car Rental Platform',
            description:
              'Professional car rental platform with real-time booking, GPS tracking, and AI-powered pricing.',
            url: process.env.NEXT_PUBLIC_APP_URL || 'http://localhost:3000',
            logo: `${process.env.NEXT_PUBLIC_APP_URL || 'http://localhost:3000'}/logo.png`,
            sameAs: [
              'https://facebook.com/carrentalplatform',
              'https://twitter.com/carrentalplatform',
              'https://instagram.com/carrentalplatform',
            ],
            contactPoint: {
              '@type': 'ContactPoint',
              telephone: '+1-800-RENTALS',
              contactType: 'customer service',
              availableLanguage: ['English'],
            },
            address: {
              '@type': 'PostalAddress',
              streetAddress: '123 Business Street',
              addressLocality: 'Business City',
              addressRegion: 'BC',
              postalCode: '12345',
              addressCountry: 'US',
            },
            priceRange: '$',
            openingHours: 'Mo-Su 00:00-23:59',
            hasOfferCatalog: {
              '@type': 'OfferCatalog',
              name: 'Vehicle Rental Services',
              itemListElement: [
                {
                  '@type': 'Offer',
                  itemOffered: {
                    '@type': 'Product',
                    name: 'Economy Car Rental',
                    description: 'Affordable economy cars for city driving',
                  },
                },
                {
                  '@type': 'Offer',
                  itemOffered: {
                    '@type': 'Product',
                    name: 'Luxury Vehicle Rental',
                    description: 'Premium luxury vehicles for special occasions',
                  },
                },
                {
                  '@type': 'Offer',
                  itemOffered: {
                    '@type': 'Product',
                    name: 'SUV Rental',
                    description: 'Spacious SUVs for family trips and adventures',
                  },
                },
              ],
            },
            aggregateRating: {
              '@type': 'AggregateRating',
              ratingValue: '4.8',
              reviewCount: '1250',
            },
            review: [
              {
                '@type': 'Review',
                author: {
                  '@type': 'Person',
                  name: 'Sarah Johnson',
                },
                reviewRating: {
                  '@type': 'Rating',
                  ratingValue: '5',
                },
                reviewBody:
                  'Excellent service! The booking process was smooth and the vehicle was in perfect condition.',
              },
            ],
          }),
        }}
      />
    </>
  )
}

// Generate static params if needed for internationalization
export function generateStaticParams() {
  return []
}

// Revalidation settings for ISR
export const revalidate = 3600 // Revalidate every hour