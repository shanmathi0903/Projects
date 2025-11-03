package dbFolder;

import beanFolder.Booking;

import java.util.ArrayList;
import java.time.LocalDate;
import java.time.format.DateTimeFormatter;
import java.util.Iterator;

public class BookingDb{
	ArrayList<Booking> booking=new ArrayList<>();

	public void addBooking(String customerName,long number,String busName,String regNo,ArrayList<Integer> bookedSeater,ArrayList<Integer> bookedSleeper,String source,String destination,String date){
		booking.add(new Booking(customerName,number,busName,regNo,bookedSeater,bookedSleeper,source,destination,date));
	}

	public void displayBookingsForBus(String busName, String regNo,String date){		
		System.out.printf("%-15s %-20s %-10s %-10s %-15s %-15s %-15s%n",
			"Customer Name", "Phone Number", "Bus Name", "RegNo", "Source", "Destination", "Seats Booked");
		System.out.println("---------------------------------------------------------------------------------------------");
		
		boolean found=false;
		
		for(Booking b:booking){
			if(b.getBusName().equals(busName) && b.getRegNo().equals(regNo) && b.getDate().equals(date)){
				String seater=(b.getSeaterSeats()==null||b.getSeaterSeats().isEmpty())?"No booking":b.getSeaterSeats().toString();
				String sleeper=(b.getSleeperSeats()==null||b.getSleeperSeats().isEmpty())?"No booking":b.getSleeperSeats().toString();

				System.out.printf("%-15s %-20s %-10s %-10s %-15s %-15s Seater: %s Sleeper: %s%n",
					b.getCustomerName(),b.getPhoneNumber(),b.getBusName(),b.getRegNo(),b.getSource(),b.getDestination(),seater,sleeper);
				found=true;
			}
		}
		if(!found){
			System.out.println("No bookings found for this bus.");
		}
	}
	
	public void showHistory(String customerName,long phoneNumber,LocalDate today){
		DateTimeFormatter formatter=DateTimeFormatter.ofPattern("dd-MM-yyyy");
		boolean found=false;
		System.out.printf("%-15s %-15s %-10s %-10s %-15s %-15s %-15s%n","Bus Name","RegNo","Source","Destination","Date","Seater","Sleeper");
		System.out.println("----------------------------------------------------------------------------------");
		for(Booking b:booking){
			try{
				LocalDate bookingDate=LocalDate.parse(b.getDate(),formatter);
				if(b.getCustomerName().equals(customerName) && b.getPhoneNumber()==phoneNumber && bookingDate.isBefore(today)){
					String seater=(b.getSeaterSeats()==null||b.getSeaterSeats().isEmpty())?"No booking":b.getSeaterSeats().toString();
					String sleeper=(b.getSleeperSeats()==null||b.getSleeperSeats().isEmpty())?"No booking":b.getSleeperSeats().toString();

					System.out.printf("%-15s %-20s %-10s %-10s %-15s %-15s Seater: %s Sleeper: %s%n",
						b.getCustomerName(),b.getPhoneNumber(),b.getBusName(),b.getRegNo(),b.getSource(),b.getDestination(),seater,sleeper);
					found=true;
				}
			}catch(Exception e){
				System.out.println("Invalid date format for booking: "+b.getDate());
			}
		}
		if(!found){
			System.out.println("No past bookings found.");
		}
	}
	
	public void futureBooking(String customerName,long phoneNumber,LocalDate today){
		DateTimeFormatter formatter=DateTimeFormatter.ofPattern("dd-MM-yyyy");
		boolean found=false;
		System.out.printf("%-15s %-15s %-15s %-15s %-15s %-15s %-15s%n","Bus Name","RegNo","Source","Destination","Date","Seater","Sleeper");
		System.out.println("----------------------------------------------------------------------------------");
		for(Booking b:booking){
			try{
				LocalDate bookingDate=LocalDate.parse(b.getDate(),formatter);
				if(b.getCustomerName().equals(customerName) && b.getPhoneNumber()==phoneNumber && bookingDate.isAfter(today)){
					String seater=(b.getSeaterSeats()==null||b.getSeaterSeats().isEmpty())?"No booking":b.getSeaterSeats().toString();
					String sleeper=(b.getSleeperSeats()==null||b.getSleeperSeats().isEmpty())?"No booking":b.getSleeperSeats().toString();
					
					System.out.printf("%-15s %-15s %-15s %-15s %-15s %-15s %-15s%n",
						b.getBusName(),b.getRegNo(),b.getSource(),b.getDestination(),b.getDate(),seater,sleeper);
					found=true;
				}
			}catch(Exception e){
				System.out.println("Invalid date format for booking: "+b.getDate());
			}
		}
		if(!found){
			System.out.println("No Future bookings is available.");
		}
	}
	
	public boolean isAlreadyBooked(String customerName,long phoneNumber,String busName,String regNo,String date){
		for(Booking b:booking){
			if(b.getCustomerName().equals(customerName) && b.getPhoneNumber()==phoneNumber && b.getRegNo().equals(regNo) && b.getDate().equals(date)){
				return true;
			}
		}
		return false;
	}
	
	public void cancelBooking(String customerName,long phoneNumber,String regNo){
		Iterator<Booking> iterator=booking.iterator();
		while(iterator.hasNext()){
			Booking b=iterator.next();
			if(b.getCustomerName().equals(customerName) && b.getPhoneNumber()==phoneNumber && b.getRegNo().equals(regNo)) {
				iterator.remove(); 
				System.out.println("Booking canceled ...");
				return;
			}
		}
		System.out.println("No booking found.");
	}
	
	public ArrayList<Integer> getBookedSiSeats(String customerName,long phoneNumber,String busName,String regNo,String date){
		ArrayList<Integer> seats=new ArrayList<>();
		for(Booking b:booking){
			if(b.getCustomerName().equals(customerName) && b.getPhoneNumber()==phoneNumber && b.getRegNo().equals(regNo) && b.getDate().equals(date)){
				seats=b.getSeaterSeats();
			}
		}
		return seats;
	}
	
	public ArrayList<Integer> getBookedSlSeats(String customerName,long phoneNumber,String busName,String regNo,String date){
		ArrayList<Integer> seats=new ArrayList<>();
		for(Booking b:booking){
			if(b.getCustomerName().equals(customerName) && b.getPhoneNumber()==phoneNumber && b.getRegNo().equals(regNo) && b.getDate().equals(date)){
				seats=b.getSleeperSeats();
			}
		}
		return seats;
	}
}
